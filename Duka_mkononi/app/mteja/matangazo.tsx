import AsyncStorage from '@react-native-async-storage/async-storage';
import { ResizeMode, Video } from 'expo-av';
import React, { useEffect, useRef, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Dimensions,
    FlatList,
    Image,
    Linking,
    Platform,
    RefreshControl,
    StyleSheet,
    Text,
    TouchableOpacity,
    View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import LogoutButton from '../../components/logout-button';
import ZoomableImage from '../../components/zoomable-image';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

const { width } = Dimensions.get('window');
import { API_BASE_URL } from '../../constants/api';

interface Matangazo {
  // NB: ids are UUID STRINGS - matangazo.blade.php's render() calls this out
  // ("ids are matched as STRINGS (UUIDs - parseInt mangles them)"). They were
  // typed as `number` here, which is wrong.
  id: string;
  user_id: string;
  title: string;
  description: string;
  media_url: string;
  media_type: 'image' | 'video';
  thumbnail_url: string;
  created_at: string;
  expires_at: string;
  payment_status: string;
  like_count: number;
  report_count: number;
  users: {
    business_name: string;
    full_name: string;
    phone: string;
    email: string;
    business_logo_url?: string | null;
  };
}

export default function MatangazoScreen() {
  const { t, lang } = useLang();

  const localeMap: Record<string, string> = {
    sw: 'sw-TZ',
    en: 'en',
    fr: 'fr-FR',
    hi: 'hi-IN',
    ur: 'ur-PK',
    es: 'es-ES',
    de: 'de-DE',
    zh: 'zh-CN',
  };

  const formatDate = (dateStr: string): string => {
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    } catch {
      return dateStr;
    }
  };

  const [matangazo, setMatangazo] = useState<Matangazo[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [likedPosts, setLikedPosts] = useState<Set<string>>(new Set());
  const [userToken, setUserToken] = useState<string | null>(null);
  const [userName, setUserName] = useState<string | null>(null);
  const [currentPlayingVideo, setCurrentPlayingVideo] = useState<string | null>(null);
  
  const videoRefs = useRef<{[key: string]: any}>({});
  const isMounted = useRef(true);
  // One like request at a time per post (same double-tap guard as the Blade page).
  const likeBusy = useRef<Set<string>>(new Set());

  // Cleanup effect
  useEffect(() => {
    return () => {
      isMounted.current = false;
      // Stop all videos on unmount
      Object.values(videoRefs.current).forEach((videoRef: any) => {
        if (videoRef && videoRef.setStatusAsync) {
          videoRef.setStatusAsync({ shouldPlay: false });
        }
      });
    };
  }, []);

  // Pakua data ya mtumiaji wakati komponenti inapopakuliwa
  useEffect(() => {
    if (isMounted.current) {
      loadUserData();
    }
    
    return () => {
      isMounted.current = false;
    };
  }, [lang]);

  useEffect(() => {
    const stop = registerLive<any>(
      'matangazo',
      async () => {
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/matangazo`, {
          headers: {
            'ngrok-skip-browser-warning': 'true',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
        });
        if (!res.ok) throw new Error('sync failed');
        return await res.json();
      },
      (data) => { if (isMounted.current) { setMatangazo(data); setLoading(false); } }
    );
    return stop;
  }, [lang]);

  const loadUserData = async () => {
    try {
      const [token, name] = await Promise.all([
        AsyncStorage.getItem('userToken'),
        AsyncStorage.getItem('userName')
      ]);
      
      if (isMounted.current) {
        console.log('Data ya mtumiaji imepakuliwa:', { token: !!token, name });
        setUserToken(token);
        setUserName(name);
        
        // Pakua matangazo baada ya kupakua data ya mtumiaji
        fetchMatangazo();
      }
    } catch (error) {
      console.error('Kosa wakati wa upakuaji wa data ya mtumiaji:', error);
      if (isMounted.current) {
        setLoading(false);
      }
    }
  };

  // Pakua matangazo kutoka kwa backend
  const fetchMatangazo = async () => {
    try {
      console.log('Inapakua matangazo kutoka:', API_BASE_URL);

      const cached = await getCache<any>('matangazo');
      if (cached && isMounted.current) { setMatangazo(cached); setLoading(false); }

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/matangazo`, {
        headers: {
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      });
      
      if (!response.ok) {
        throw new Error(`Hitilafu ya HTTP! status: ${response.status}`);
      }
      
      const data = await response.json();
      console.log('Data ya matangazo imepokelewa:', data.length, 'rekodi');
      
      if (isMounted.current) {
        setMatangazo(data);
        setCache('matangazo', data).catch(() => {});
        
        // Pakua matangazo yaliyopendwa ikiwa mtumiaji ameingia
        if (userToken) {
          fetchUserLikes();
        }
      }
      
    } catch (error) {
      console.error('Kosa wakati wa upakuaji wa matangazo:', error);
      if (isMounted.current) {
        const cached = await getCache<any>('matangazo');
        if (cached) { setMatangazo(cached); setLoading(false); setRefreshing(false); return; }
        Alert.alert(t('app.error'), t('customer_dashboard.error_loading'));
        setMatangazo([]);
      }
    } finally {
      if (isMounted.current) {
        setLoading(false);
        setRefreshing(false);
      }
    }
  };

  // Pakua matangazo yaliyopendwa na mtumiaji
  const fetchUserLikes = async () => {
    if (!userToken || !isMounted.current) return;

    try {
      const cached = await getCache<any>('reactions:likes');
      if (cached && isMounted.current && cached.likedPosts) {
        setLikedPosts(new Set(cached.likedPosts));
      }

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/reactions/user/likes`, {
        headers: {
          'Authorization': `Bearer ${userToken}`,
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json',
        },
      });
      
      if (response.ok && isMounted.current) {
        const data = await response.json();
        console.log('Matangazo yaliyopendwa na mtumiaji:', data.likedPosts);
        setLikedPosts(new Set(data.likedPosts));
        setCache('reactions:likes', data).catch(() => {});
      }
    } catch (error) {
      console.error('Kosa wakati wa upakuaji wa matangazo yaliyopendwa:', error);
    }
  };

  // Shughulikia kitendo cha kupenda
  const handleLike = async (postId: string) => {
    if (!userToken) {
      Alert.alert(t('customer_dashboard.adverts_title'), `${t('customer_dashboard.login_needed')} ${t('customer_dashboard.like')}.`);
      return;
    }

    if (likeBusy.current.has(postId)) return;
    likeBusy.current.add(postId);

    if (!(await requireNetwork())) { likeBusy.current.delete(postId); return; }

    try {
      console.log('Inapenda tangazo:', postId);
      
      const wasLiked = likedPosts.has(postId);
      const currentLikes = matangazo.find(m => m.id === postId)?.like_count || 0;
      
      // Sasisha matangazo yaliyopendwa mara moja
      if (isMounted.current) {
        setLikedPosts(prev => {
          const newSet = new Set(prev);
          if (wasLiked) {
            newSet.delete(postId);
          } else {
            newSet.add(postId);
          }
          return newSet;
        });

        // Sasisha matangazo array mara moja
        setMatangazo(prev => prev.map(post => {
          if (post.id === postId) {
            return {
              ...post,
              like_count: wasLiked ? Math.max(post.like_count - 1, 0) : post.like_count + 1
            };
          }
          return post;
        }));
      }

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/reactions/like`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${userToken}`,
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          matangazo_id: postId
        }),
      });

      const result = await response.json();
      console.log('Jibu la kupenda:', result);

      if (response.ok && isMounted.current) {
        // Seva ndiyo chanzo cha ukweli (kama Blade): tumia `liked` na idadi
        // zote mbili kutoka kwenye jibu, si sasisho la matumaini pekee.
        setLikedPosts(prev => {
          const newSet = new Set(prev);
          if (result.liked) newSet.add(postId);
          else newSet.delete(postId);
          return newSet;
        });
        setMatangazo(prev => prev.map(post => {
          if (post.id === postId) {
            return {
              ...post,
              like_count: result.like_count !== undefined ? result.like_count : post.like_count,
              report_count: result.report_count !== undefined ? result.report_count : post.report_count
            };
          }
          return post;
        }));
      } else if (isMounted.current) {
        // Rejesha sasisho la matumaini ikiwa kuna kosa
        setLikedPosts(prev => {
          const newSet = new Set(prev);
          if (wasLiked) {
            newSet.add(postId);
          } else {
            newSet.delete(postId);
          }
          return newSet;
        });

        // Rejesha matangazo array
        setMatangazo(prev => prev.map(post => {
          if (post.id === postId) {
            return {
              ...post,
              like_count: currentLikes
            };
          }
          return post;
        }));

        Alert.alert(t('app.error'), result.error || t('customer_dashboard.error_like'));
        
        if (response.status === 401) {
          await AsyncStorage.multiRemove(['userToken', 'isLoggedIn']);
          setUserToken(null);
        }
      }
    } catch (error) {
      console.error('Kosa la kupenda:', error);
      
      if (isMounted.current) {
        // Rejesha sasisho la matumaini kwa kosa la mtandao
        const wasLiked = likedPosts.has(postId);
        const currentLikes = matangazo.find(m => m.id === postId)?.like_count || 0;
        
        setLikedPosts(prev => {
          const newSet = new Set(prev);
          if (wasLiked) {
            newSet.add(postId);
          } else {
            newSet.delete(postId);
          }
          return newSet;
        });

        setMatangazo(prev => prev.map(post => {
          if (post.id === postId) {
            return {
              ...post,
              like_count: currentLikes
            };
          }
          return post;
        }));

        Alert.alert(t('app.error'), `${t('customer_dashboard.error_like')} ${t('customer_dashboard.retry_network')}`);
      }
    } finally {
      likeBusy.current.delete(postId);
    }
  };

  // Shughulikia kitendo cha kuripoti
  const handleReport = async (post: Matangazo) => {
    if (!userToken) {
      Alert.alert(t('customer_dashboard.adverts_title'), `${t('customer_dashboard.login_needed')} ${t('customer_dashboard.report')}.`);
      return;
    }

    Alert.alert(
      t('customer_dashboard.report_advert'),
      `${t('customer_dashboard.report')} "${post.users?.business_name || ''}"?`,
      [
        { text: t('app.cancel'), style: 'cancel' },
        { 
          text: t('customer_dashboard.report'), 
          style: 'destructive',
          onPress: async () => {
            if (!(await requireNetwork())) return;
            try {
              // Sasisho la matumaini
              const currentReports = post.report_count || 0;
              
              if (isMounted.current) {
                setMatangazo(prev => prev.map(p => {
                  if (p.id === post.id) {
                    return {
                      ...p,
                      report_count: currentReports + 1
                    };
                  }
                  return p;
                }));
              }

              const response = await fetchWithTimeout(`${API_BASE_URL}/api/reactions/report`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Authorization': `Bearer ${userToken}`,
                  'ngrok-skip-browser-warning': 'true',
                  'Accept': 'application/json',
                },
                body: JSON.stringify({
                  matangazo_id: post.id
                }),
              });

              if (response.ok) {
                // Reconcile with the server's authoritative count (Blade does this).
                const data = await response.json().catch(() => ({} as any));
                if (isMounted.current && data.report_count !== undefined) {
                  setMatangazo(prev => prev.map(p => (
                    p.id === post.id ? { ...p, report_count: data.report_count } : p
                  )));
                }
                Alert.alert(t('customer_dashboard.thank_you'), t('customer_dashboard.reported'));
              } else if (isMounted.current) {
                // Rejesha sasisho la matumaini kwa kosa
                setMatangazo(prev => prev.map(p => {
                  if (p.id === post.id) {
                    return {
                      ...p,
                      report_count: currentReports
                    };
                  }
                  return p;
                }));

                const errorData = await response.json();
                Alert.alert(t('app.error'), errorData.error || t('customer_dashboard.error_loading'));
                
                if (response.status === 401) {
                  await AsyncStorage.multiRemove(['userToken', 'isLoggedIn']);
                  setUserToken(null);
                }
              }
            } catch (error) {
              console.error('Kosa la kuripoti:', error);
              
              if (isMounted.current) {
                // Rejesha sasisho la matumaini kwa kosa la mtandao
                const currentReports = post.report_count || 0;
                setMatangazo(prev => prev.map(p => {
                  if (p.id === post.id) {
                    return {
                      ...p,
                      report_count: currentReports
                    };
                  }
                  return p;
                }));
                Alert.alert(t('app.error'), t('customer_dashboard.retry_network'));
              }
            }
          }
        }
      ]
    );
  };

  // Shughulikia kitendo cha kuagiza
  const handleOrder = (post: Matangazo) => {
    const phoneNumber = post.users?.phone || '';
    const businessName = post.users?.business_name || post.title || t('customer_dashboard.business');
    
    if (!phoneNumber || phoneNumber.trim() === '' || phoneNumber === 'Hakuna namba ya simu') {
      Alert.alert(t('app.info'), t('customer_dashboard.no_phone'));
      return;
    }

    Alert.alert(
      `${t('customer_dashboard.contact')} ${businessName}`,
      `${t('customer_dashboard.choose_method')} ${businessName}:`,
      [
        { text: t('app.cancel'), style: 'cancel' },
        { 
          text: `📞 ${t('customer_dashboard.call')}`, 
          onPress: () => makePhoneCall(phoneNumber)
        },
        { 
          text: `💬 ${t('customer_dashboard.sms')}`, 
          onPress: () => sendSMS(phoneNumber, businessName)
        }
      ]
    );
  };

  // Normalise to the +255 international form - the same rule as the Blade
  // page's formatPhoneNumber() and as mteja/biashara.tsx. This screen used to
  // dial the raw number without the country code.
  const formatPhoneNumber = (phoneNumber: string): string | null => {
    if (!phoneNumber || phoneNumber === 'null' || phoneNumber === 'undefined' ||
        phoneNumber === 'Haijajazwa' || phoneNumber === 'Hakuna namba ya simu') {
      return null;
    }
    let clean = phoneNumber.replace(/[\s\-\(\)]/g, '');
    if (!clean.startsWith('+255') && !clean.startsWith('255')) {
      if (clean.length === 9) {
        clean = `255${clean}`;
      } else if (clean.length === 10 && clean.startsWith('0')) {
        clean = `255${clean.substring(1)}`;
      }
    }
    return clean;
  };

  // Kazi ya kupiga simu
  const makePhoneCall = (phoneNumber: string) => {
    const formatted = formatPhoneNumber(phoneNumber);
    if (!formatted) {
      Alert.alert(t('app.info'), t('customer_dashboard.no_phone'));
      return;
    }
    const phoneURL = `tel:${formatted}`;
    
    Linking.canOpenURL(phoneURL)
      .then((supported) => {
        if (supported) {
          Linking.openURL(phoneURL)
            .then(() => console.log('Simu imezinduliwa'))
            .catch((err) => {
              console.error('Kosa wakati wa kufungua kipiga simu:', err);
              Alert.alert(t('app.error'), t('customer_dashboard.error_loading'));
            });
        } else {
          Alert.alert(t('app.error'), t('customer_dashboard.no_phone'));
        }
      })
      .catch((err) => {
        console.error('Kosa wakati wa kuangalia usaidizi wa kupiga simu:', err);
        Alert.alert(t('app.error'), t('customer_dashboard.error_loading'));
      });
  };

  // Kazi ya kutuma ujumbe
  const sendSMS = (phoneNumber: string, businessName: string) => {
    const formatted = formatPhoneNumber(phoneNumber);
    if (!formatted) {
      Alert.alert(t('app.info'), t('customer_dashboard.no_phone'));
      return;
    }

    let smsURL;
    const message = t('customer_dashboard.sms_message_advert').replace('{name}', businessName);

    if (Platform.OS === 'ios') {
      smsURL = `sms:${formatted}&body=${encodeURIComponent(message)}`;
    } else {
      smsURL = `sms:${formatted}?body=${encodeURIComponent(message)}`;
    }

    Linking.canOpenURL(smsURL)
      .then((supported) => {
        if (supported) {
          Linking.openURL(smsURL)
            .then(() => console.log('Programu ya ujumbe imefunguliwa'))
            .catch((err) => {
              console.error('Kosa wakati wa kufungua programu ya ujumbe:', err);
              Alert.alert(t('app.error'), t('customer_dashboard.error_loading'));
            });
        } else {
          Alert.alert(t('app.error'), t('customer_dashboard.no_phone'));
        }
      })
      .catch((err) => {
        console.error('Kosa wakati wa kuangalia usaidizi wa SMS:', err);
        Alert.alert(t('app.error'), t('customer_dashboard.error_loading'));
      });
  };

  // Kudhibiti uchezaji wa video
  const handleVideoPlayback = async (postId: string, videoRef: any) => {
    if (currentPlayingVideo && currentPlayingVideo !== postId) {
      const previousVideoRef = videoRefs.current[currentPlayingVideo];
      if (previousVideoRef) {
        await previousVideoRef.setStatusAsync({ shouldPlay: false });
      }
    }

    setCurrentPlayingVideo(postId);
    
    if (videoRef) {
      await videoRef.setStatusAsync({ shouldPlay: true });
    }
  };

  const renderMedia = (item: Matangazo) => {
    if (!item.media_url) {
      return (
        <View style={[styles.media, styles.unknownMedia]}>
          <Text style={styles.unknownText}>{t('customer_dashboard.no_media')}</Text>
        </View>
      );
    }

    if (item.media_type === 'video') {
      return (
        <TouchableOpacity
          activeOpacity={1}
          onPress={() => {
            const videoRef = videoRefs.current[item.id];
            if (videoRef) {
              if (currentPlayingVideo === item.id) {
                videoRef.setStatusAsync({ shouldPlay: false });
                setCurrentPlayingVideo(null);
              } else {
                handleVideoPlayback(item.id, videoRef);
              }
            }
          }}
        >
          <Video
            ref={(ref) => {
              videoRefs.current[item.id] = ref;
            }}
            source={{ uri: item.media_url }}
            style={styles.media}
            useNativeControls={false}
            resizeMode={ResizeMode.COVER}
            isLooping
            shouldPlay={currentPlayingVideo === item.id}
            onPlaybackStatusUpdate={(status) => {
              if (!status.isLoaded) {
                return;
              }
            }}
          />
          <View style={styles.videoOverlay}>
            {currentPlayingVideo !== item.id && (
              <View style={styles.playButtonOverlay}>
                <Text style={styles.playButton}>▶</Text>
              </View>
            )}
          </View>
        </TouchableOpacity>
      );
    } else {
      return (
        <Image 
          source={{ uri: item.media_url }} 
          style={styles.media}
          resizeMode="cover"
        />
      );
    }
  };

  const renderItem = ({ item }: { item: Matangazo }) => {
    const isLiked = likedPosts.has(item.id);
    
    const businessName = item.users?.business_name || item.title || t('customer_dashboard.unnamed_business');
    const ownerName = item.users?.full_name || t('customer_dashboard.seller');
    const phoneNumber = item.users?.phone || t('customer_dashboard.not_provided');
    const email = item.users?.email || '';
    
    return (
      <View style={styles.postContainer}>
        <View style={styles.header}>
          <View style={styles.businessInfo}>
            {item.users?.business_logo_url ? (
              <ZoomableImage
                uri={item.users.business_logo_url}
                style={styles.avatar}
                name={businessName}
              />
            ) : (
              <View style={styles.avatar}>
                <Text style={styles.avatarText}>
                  {businessName?.charAt(0).toUpperCase() || 'B'}
                </Text>
              </View>
            )}
            <View style={styles.businessDetails}>
              <Text style={styles.businessName}>
                {businessName}
              </Text>
              <Text style={styles.ownerName}>
                {ownerName}
              </Text>
            </View>
          </View>
          <TouchableOpacity 
            style={styles.orderButton}
            onPress={() => handleOrder(item)}
          >
            <Text style={styles.orderButtonText}>{t('customer_dashboard.order')}</Text>
          </TouchableOpacity>
        </View>

        {renderMedia(item)}

        <View style={styles.actions}>
          <TouchableOpacity 
            style={[styles.actionButton, isLiked && styles.likedButton]}
            onPress={() => handleLike(item.id)}
          >
            <Text style={[
              styles.actionText,
              isLiked && styles.likedText
            ]}>
              {isLiked ? '❤️' : '🤍'} {t('customer_dashboard.like')} ({item.like_count || 0})
            </Text>
          </TouchableOpacity>
          
          <TouchableOpacity 
            style={styles.actionButton}
            onPress={() => handleReport(item)}
          >
            <Text style={styles.actionText}>
              ⚠️ {t('customer_dashboard.report')} ({item.report_count || 0})
            </Text>
          </TouchableOpacity>
        </View>

        <View style={styles.descriptionContainer}>
          <Text style={styles.description}>
            <Text style={styles.businessNameText}>
              {businessName}:
            </Text>{' '}
            {item.description}
          </Text>
        </View>

        <View style={styles.contactContainer}>
          <Text style={styles.contactTitle}>📞 {t('customer_dashboard.contact_us')}</Text>
          <Text style={styles.phone}>{phoneNumber}</Text>
          {email ? (
            <Text style={styles.email}>📧 {email}</Text>
          ) : null}
          <Text style={styles.businessContact}>
            {t('customer_dashboard.business')} {businessName}
          </Text>
        </View>

        <View style={styles.footer}>
          <Text style={styles.timestamp}>
            {t('customer_dashboard.published')} {formatDate(item.created_at)}
          </Text>
        </View>
      </View>
    );
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchMatangazo();
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
          <Text style={styles.loadingText}>{t('customer_dashboard.loading_adverts')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <View style={styles.container}>
      {/* Logout */}
      <View style={styles.topBar}>
        <LogoutButton iconOnly />
      </View>
      <FlatList
        data={matangazo}
        renderItem={renderItem}
        keyExtractor={(item) => item.id.toString()}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.empty}>
            <Text style={styles.emptyText}>{t('customer_dashboard.no_adverts')}</Text>
            <Text style={styles.emptySubtext}>{t('customer_dashboard.first_advert')}</Text>
          </View>
        }
        ListHeaderComponent={
          <View style={styles.listHeader}>
            <Text style={styles.screenTitle}>{t('customer_dashboard.adverts_title')}</Text>
            <Text style={styles.screenSubtitle}>
              {userName ? `${t('customer_dashboard.welcome').replace('{name}', userName)} ` : ''}
              {t('customer_dashboard.discover')}
            </Text>
            {matangazo.length > 0 && (
              <Text style={styles.infoText}>
                📊 {matangazo.length} {t('customer_dashboard.adverts_available')}
              </Text>
            )}
            {!userToken && (
              <View style={styles.loginWarning}>
                <Text style={styles.loginWarningText}>
                  ⚠️ {t('customer_dashboard.login_warning')}
                </Text>
              </View>
            )}
          </View>
        }
      />
    </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screenSafe: {
    flex: 1,
    backgroundColor: '#f8f8f8',
  },
  container: {
    flex: 1,
  },
  topBar: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: '#f8f8f8',
  },
  listHeader: {
    padding: 20,
    backgroundColor: 'white',
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  screenTitle: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 5,
  },
  screenSubtitle: {
    fontSize: 16,
    color: '#666',
    marginBottom: 10,
  },
  infoText: {
    fontSize: 14,
    color: '#2c3e50',
    backgroundColor: '#e8f4fd',
    padding: 10,
    borderRadius: 8,
    marginTop: 5,
    marginBottom: 10,
    fontWeight: '600',
  },
  loginWarning: {
    backgroundColor: '#fff3cd',
    padding: 10,
    borderRadius: 8,
    borderLeftWidth: 4,
    borderLeftColor: '#ffc107',
    marginTop: 5,
  },
  loginWarningText: {
    fontSize: 14,
    color: '#856404',
  },
  postContainer: {
    backgroundColor: 'white',
    marginBottom: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#eee',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 15,
  },
  businessInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#FF6B35',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },
  avatarText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 16,
  },
  businessDetails: {
    flex: 1,
  },
  businessName: {
    fontWeight: 'bold',
    fontSize: 16,
    color: '#333',
  },
  ownerName: {
    fontSize: 12,
    color: '#666',
    marginTop: 2,
  },
  businessNameText: {
    fontWeight: 'bold',
    color: '#333',
  },
  orderButton: {
    backgroundColor: '#27ae60',
    paddingHorizontal: 20,
    paddingVertical: 8,
    borderRadius: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  orderButtonText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 14,
  },
  media: {
    width: width,
    height: width,
    backgroundColor: '#000',
  },
  unknownMedia: {
    backgroundColor: '#ccc',
    justifyContent: 'center',
    alignItems: 'center',
  },
  unknownText: {
    color: '#666',
    fontSize: 16,
  },
  videoOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
  },
  playButtonOverlay: {
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    width: 60,
    height: 60,
    borderRadius: 30,
    justifyContent: 'center',
    alignItems: 'center',
  },
  playButton: {
    color: 'white',
    fontSize: 24,
    marginLeft: 4,
  },
  actions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    padding: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  actionButton: {
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 6,
    backgroundColor: '#f8f9fa',
  },
  likedButton: {
    backgroundColor: '#ffe6e6',
  },
  actionText: {
    fontSize: 14,
    color: '#333',
    fontWeight: '600',
  },
  likedText: {
    color: '#e74c3c',
  },
  descriptionContainer: {
    padding: 15,
    paddingBottom: 10,
  },
  description: {
    fontSize: 14,
    lineHeight: 18,
    color: '#333',
  },
  contactContainer: {
    padding: 15,
    paddingTop: 0,
  },
  contactTitle: {
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 5,
  },
  phone: {
    color: '#007AFF',
    fontWeight: '600',
    fontSize: 16,
    marginBottom: 5,
  },
  email: {
    color: '#3498db',
    fontWeight: '500',
    marginBottom: 5,
    fontSize: 14,
  },
  businessContact: {
    fontSize: 14,
    color: '#333',
    fontStyle: 'italic',
  },
  footer: {
    padding: 15,
    paddingTop: 5,
  },
  timestamp: {
    fontSize: 12,
    color: '#999',
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  empty: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 50,
    marginTop: 100,
  },
  emptyText: {
    fontSize: 18,
    color: '#666',
    textAlign: 'center',
    marginBottom: 10,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#999',
    textAlign: 'center',
  },
});