import { useRouter } from 'expo-router';
import React, { useEffect, useRef, useState } from 'react';
import {
  Dimensions,
  Modal,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  ScrollView,
  Animated,
  Easing,
  FlatList
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang, Lang, LANGUAGE_NAMES, LANGUAGE_FLAGS, ALL_LANGUAGES } from '../../context/LanguageContext';

const { width, height } = Dimensions.get('window');

export default function HomeScreen() {
  const router = useRouter();
  const { lang, changeLang, t, languageNames, languageFlags, availableLanguages } = useLang();
  
  // Language selector modal state
  const [showLanguageModal, setShowLanguageModal] = useState(false);
  const [selectedLang, setSelectedLang] = useState<Lang>(lang);
  
  // Fade in animation — the root layout guarantees this screen only shows when logged out
  useEffect(() => {
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 1,
        duration: 800,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      }),
      Animated.timing(headerAnim, {
        toValue: 1,
        duration: 1200,
        easing: Easing.out(Easing.cubic),
        useNativeDriver: true,
      })
    ]).start();
  }, []);
  
  // Animated values
  const fadeAnim = useRef(new Animated.Value(0)).current;
  const buttonScale = useRef(new Animated.Value(1)).current;
  const headerAnim = useRef(new Animated.Value(0)).current;
  const modalAnim = useRef(new Animated.Value(0)).current;
  
  const buttons = [
    { 
      text: t('buttons.mteja'), 
      color: '#3498db', 
      type: 'mteja',
      icon: 'people-outline' as const,
      description: t('buttons.mteja_description')
    },
    { 
      text: t('buttons.muuzaji'), 
      color: '#2ecc71', 
      type: 'muuzaji',
      icon: 'cart-outline' as const,
      description: t('buttons.muuzaji_description')
    },
    { 
      text: t('buttons.msimamizi'), 
      color: '#e74c3c', 
      type: 'msimamizi',
      icon: 'shield-checkmark-outline' as const,
      description: t('buttons.msimamizi_description')
    },
    { 
      text: t('buttons.washa'), 
      color: '#9b59b6', 
      type: 'washa',
      icon: 'flash-outline' as const,
      description: t('buttons.washa_description')
    }
  ];

  const handleButtonPress = (buttonType: string) => {
    // Button press animation
    Animated.sequence([
      Animated.timing(buttonScale, {
        toValue: 0.95,
        duration: 100,
        useNativeDriver: true,
      }),
      Animated.timing(buttonScale, {
        toValue: 1,
        duration: 100,
        useNativeDriver: true,
      }),
    ]).start();

    if (buttonType === 'washa') {
      router.push({
        pathname: '/malipo',
        params: { lang }
      });
    } else {
      router.push({
        pathname: '/login',
        params: { role: buttonType, lang }
      });
    }
  };

  const openLanguageModal = () => {
    setSelectedLang(lang);
    setShowLanguageModal(true);
    Animated.timing(modalAnim, {
      toValue: 1,
      duration: 300,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start();
  };

  const closeLanguageModal = () => {
    Animated.timing(modalAnim, {
      toValue: 0,
      duration: 200,
      easing: Easing.in(Easing.cubic),
      useNativeDriver: true,
    }).start(() => {
      setShowLanguageModal(false);
    });
  };

  const applyLanguage = async () => {
    await changeLang(selectedLang);
    closeLanguageModal();
  };

  const handleLangSelect = (l: Lang) => {
    setSelectedLang(l);
  };

  const headerTranslateY = headerAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [30, 0]
  });

  const modalTranslateY = modalAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [300, 0]
  });

  return (
    <SafeAreaView style={styles.safeArea}>
      <Animated.View 
        style={[
          styles.scrollView,
          {
            opacity: fadeAnim,
            transform: [
              {
                translateY: fadeAnim.interpolate({
                  inputRange: [0, 1],
                  outputRange: [20, 0]
                })
              }
            ]
          }
        ]}
      >
        <ScrollView 
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.container}>
            <StatusBar barStyle="dark-content" backgroundColor="#ffffff" />
            
            {/* Language Switcher Button - Top Right */}
            <TouchableOpacity 
              style={styles.languageSwitcher}
              onPress={openLanguageModal}
              activeOpacity={0.7}
            >
              <Ionicons name="language-outline" size={20} color="#2c3e50" />
              <Text style={styles.languageSwitcherText}>
                {languageFlags[lang]} {languageNames[lang]}
              </Text>
              <Ionicons name="chevron-down" size={16} color="#7f8c8d" />
            </TouchableOpacity>

            {/* Header */}
            <Animated.View 
              style={[
                styles.headerContainer,
                {
                  opacity: headerAnim,
                  transform: [{ translateY: headerTranslateY }]
                }
              ]}
            >
              <View style={styles.logoContainer}>
                <View style={styles.logoCircle}>
                  <Ionicons name="storefront-outline" size={50} color="#ffffff" />
                </View>
              </View>
              
              <Text style={styles.header}>
                {t('home.welcome')}
              </Text>
              <Text style={styles.subHeader}>
                {t('home.subtitle')}
              </Text>
            </Animated.View>

            {/* Buttons Section */}
            <View style={styles.buttonContainer}>
              {buttons.map((button, index) => (
                <Animated.View
                  key={button.type}
                  style={[
                    styles.buttonWrapper,
                    {
                      opacity: fadeAnim,
                      transform: [
                        {
                          translateY: fadeAnim.interpolate({
                            inputRange: [0, 1],
                            outputRange: [20, 0]
                          })
                        },
                        { scale: buttonScale }
                      ]
                    }
                  ]}
                >
                  <TouchableOpacity
                    style={[styles.button, { backgroundColor: button.color }]}
                    onPress={() => handleButtonPress(button.type)}
                    activeOpacity={0.8}
                  >
                    <View style={styles.buttonContent}>
                      <View style={[styles.iconContainer, { backgroundColor: `${button.color}20` }]}>
                        <Ionicons 
                          name={button.icon} 
                          size={28} 
                          color="white" 
                        />
                      </View>
                      <View style={styles.buttonTextContainer}>
                        <Text style={styles.buttonText}>{button.text}</Text>
                        <Text style={styles.buttonDescription}>
                          {button.description}
                        </Text>
                      </View>
                      <Ionicons 
                        name="chevron-forward-outline" 
                        size={24} 
                        color="rgba(255,255,255,0.8)" 
                      />
                    </View>
                  </TouchableOpacity>
                </Animated.View>
              ))}
            </View>

            {/* Info Section */}
            <Animated.View 
              style={[
                styles.infoContainer,
                {
                  opacity: fadeAnim,
                  transform: [
                    {
                      translateY: fadeAnim.interpolate({
                        inputRange: [0, 1],
                        outputRange: [30, 0]
                      })
                    }
                  ]
                }
              ]}
            >
              <View style={styles.infoCard}>
                <View style={styles.infoIconContainer}>
                  <Ionicons name="information-circle-outline" size={40} color="#3498db" />
                </View>
                <Text style={styles.infoTitle}>{t('instructions.title')}</Text>
                <Text style={styles.infoText}>
                  {t('instructions.step1')}{"\n"}
                  {t('instructions.step2')}{"\n"}
                  {t('instructions.step3')}
                </Text>
              </View>
            </Animated.View>

            {/* Security Warning Section */}
            <Animated.View 
              style={[
                styles.warningContainer,
                {
                  opacity: fadeAnim,
                  transform: [
                    {
                      translateY: fadeAnim.interpolate({
                        inputRange: [0, 1],
                        outputRange: [30, 0]
                      })
                    }
                  ]
                }
              ]}
            >
              <View style={styles.warningHeader}>
                <View style={styles.warningIconContainer}>
                  <Ionicons name="shield-checkmark-outline" size={24} color="#ffffff" />
                </View>
                <Text style={styles.warningTitle}>{t('security.title')}</Text>
              </View>
              
              <Text style={styles.warningText}>
                {t('security.warning')}
              </Text>
              
              <View style={styles.warningPoints}>
                {['point1', 'point2', 'point3', 'point4'].map((point, index) => (
                  <View key={index} style={styles.warningPoint}>
                    <Ionicons name="checkmark-circle" size={18} color="#27ae60" />
                    <Text style={styles.warningPointText}>{t(`security.${point}`)}</Text>
                  </View>
                ))}
              </View>
            </Animated.View>

            {/* Footer */}
            <Animated.View 
              style={[
                styles.footer,
                {
                  opacity: fadeAnim
                }
              ]}
            >
              <Text style={styles.footerText}>
                © {new Date().getFullYear()} Dukani App
              </Text>
              <Text style={styles.footerSubText}>
                {t('footer.security_note')}
              </Text>
            </Animated.View>
          </View>
        </ScrollView>
      </Animated.View>

      {/* Language Selector Modal */}
      <Modal
        visible={showLanguageModal}
        transparent
        animationType="none"
        onRequestClose={closeLanguageModal}
      >
        <TouchableOpacity 
          style={styles.modalOverlay}
          activeOpacity={1}
          onPress={closeLanguageModal}
        >
          <Animated.View 
            style={[
              styles.modalContent,
              {
                transform: [{ translateY: modalTranslateY }],
                opacity: modalAnim,
              }
            ]}
          >
            <TouchableOpacity activeOpacity={1}>
              {/* Modal Handle */}
              <View style={styles.modalHandle} />
              
              {/* Modal Header */}
              <View style={styles.modalHeader}>
                <View style={styles.modalIconContainer}>
                  <Ionicons name="language-outline" size={28} color="#3498db" />
                </View>
                <Text style={styles.modalTitle}>{t('app.badili_lugha') || 'Badili Lugha'}</Text>
                <Text style={styles.modalSubtitle}>
                  {t('home.subtitle')}
                </Text>
              </View>

              {/* Language List */}
              <ScrollView 
                style={styles.languageList}
                showsVerticalScrollIndicator={false}
              >
                {availableLanguages.map((l: Lang) => {
                  const isSelected = selectedLang === l;
                  const isCurrent = lang === l;
                  return (
                    <TouchableOpacity
                      key={l}
                      style={[
                        styles.languageItem,
                        isSelected && styles.languageItemSelected,
                        isCurrent && !isSelected && styles.languageItemCurrent,
                      ]}
                      onPress={() => handleLangSelect(l)}
                      activeOpacity={0.7}
                    >
                      <View style={styles.languageItemLeft}>
                        <Text style={styles.languageFlag}>{languageFlags[l]}</Text>
                        <View style={styles.languageInfo}>
                          <Text style={[
                            styles.languageName,
                            isSelected && styles.languageNameSelected,
                          ]}>
                            {languageNames[l]}
                          </Text>
                          <Text style={styles.languageCode}>{l.toUpperCase()}</Text>
                        </View>
                      </View>
                      <View style={[
                        styles.radioOuter,
                        isSelected && styles.radioOuterSelected,
                      ]}>
                        {isSelected && <View style={styles.radioInner} />}
                      </View>
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>

              {/* Action Buttons */}
              <View style={styles.modalActions}>
                <TouchableOpacity 
                  style={styles.cancelButton}
                  onPress={closeLanguageModal}
                  activeOpacity={0.7}
                >
                  <Text style={styles.cancelButtonText}>
                    {t('app.funga') || 'Funga'}
                  </Text>
                </TouchableOpacity>
                
                <TouchableOpacity 
                  style={[
                    styles.applyButton,
                    selectedLang === lang && styles.applyButtonDisabled,
                  ]}
                  onPress={applyLanguage}
                  activeOpacity={0.8}
                  disabled={selectedLang === lang}
                >
                  <Ionicons name="checkmark-circle" size={20} color="white" />
                  <Text style={styles.applyButtonText}>
                    {t('app.tuma') || 'Tuma'}
                  </Text>
                </TouchableOpacity>
              </View>
            </TouchableOpacity>
          </Animated.View>
        </TouchableOpacity>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
  },
  container: {
    flex: 1,
    alignItems: 'center',
    backgroundColor: '#ffffff',
    paddingHorizontal: 20,
    paddingVertical: 30,
    minHeight: height,
  },
  // Language Switcher Button
  languageSwitcher: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-end',
    backgroundColor: '#f0f4f8',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
    marginBottom: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 2,
  },
  languageSwitcherText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
    marginHorizontal: 6,
  },
  headerContainer: {
    alignItems: 'center',
    marginBottom: 40,
    marginTop: 10,
    width: '100%',
  },
  logoContainer: {
    marginBottom: 25,
  },
  logoCircle: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#3498db',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#3498db',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 15,
    elevation: 8,
  },
  header: {
    fontSize: 32,
    fontWeight: 'bold',
    textAlign: 'center',
    marginTop: 10,
    color: '#2c3e50',
    letterSpacing: 0.5,
  },
  subHeader: {
    fontSize: 16,
    textAlign: 'center',
    marginTop: 8,
    color: '#7f8c8d',
    letterSpacing: 0.5,
  },
  buttonContainer: {
    width: '100%',
    alignItems: 'center',
    marginBottom: 30,
  },
  buttonWrapper: {
    width: '100%',
    alignItems: 'center',
    marginBottom: 16,
  },
  button: {
    width: width * 0.9,
    height: 85,
    justifyContent: 'center',
    paddingHorizontal: 25,
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 5,
    position: 'relative',
    overflow: 'hidden',
  },
  buttonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  iconContainer: {
    width: 50,
    height: 50,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 15,
  },
  buttonTextContainer: {
    flex: 1,
  },
  buttonText: {
    fontSize: 20,
    fontWeight: 'bold',
    color: 'white',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  buttonDescription: {
    fontSize: 12,
    color: 'rgba(255,255,255,0.9)',
    marginTop: 4,
    letterSpacing: 0.3,
  },
  infoContainer: {
    width: '100%',
    marginBottom: 30,
  },
  infoCard: {
    backgroundColor: '#f8f9fa',
    padding: 25,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#e9ecef',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  infoIconContainer: {
    marginBottom: 15,
  },
  infoTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 10,
  },
  infoText: {
    fontSize: 15,
    color: '#5d6d7e',
    textAlign: 'center',
    lineHeight: 22,
  },
  warningContainer: {
    width: width * 0.9,
    padding: 25,
    backgroundColor: '#fff3cd',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#ffeaa7',
    marginBottom: 25,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 2,
  },
  warningHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 15,
  },
  warningIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#856404',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },
  warningTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#856404',
    textAlign: 'center',
  },
  warningText: {
    fontSize: 14,
    color: '#856404',
    textAlign: 'center',
    marginBottom: 20,
    lineHeight: 20,
    fontWeight: '500',
  },
  warningPoints: {
    gap: 12,
  },
  warningPoint: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  warningPointText: {
    fontSize: 13,
    color: '#856404',
    marginLeft: 10,
    flex: 1,
    lineHeight: 18,
  },
  footer: {
    alignItems: 'center',
    marginTop: 25,
    paddingTop: 25,
    borderTopWidth: 1,
    borderTopColor: '#e9ecef',
    width: '100%',
  },
  footerText: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'center',
    marginBottom: 4,
    fontWeight: '500',
  },
  footerSubText: {
    fontSize: 12,
    color: '#95a5a6',
    textAlign: 'center',
  },
  // Modal Styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: '#ffffff',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    paddingHorizontal: 24,
    paddingTop: 12,
    paddingBottom: 40,
    maxHeight: height * 0.75,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -5 },
    shadowOpacity: 0.1,
    shadowRadius: 15,
    elevation: 10,
  },
  modalHandle: {
    width: 40,
    height: 4,
    backgroundColor: '#e0e0e0',
    borderRadius: 2,
    alignSelf: 'center',
    marginBottom: 16,
  },
  modalHeader: {
    alignItems: 'center',
    marginBottom: 24,
  },
  modalIconContainer: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#e8f4fd',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  modalTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
  },
  modalSubtitle: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'center',
  },
  languageList: {
    maxHeight: height * 0.4,
    marginBottom: 20,
  },
  languageItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderRadius: 12,
    marginBottom: 8,
    backgroundColor: '#f8f9fa',
    borderWidth: 1.5,
    borderColor: 'transparent',
  },
  languageItemSelected: {
    backgroundColor: '#e8f4fd',
    borderColor: '#3498db',
  },
  languageItemCurrent: {
    backgroundColor: '#f8f9fa',
    borderColor: '#e0e0e0',
  },
  languageItemLeft: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  languageFlag: {
    fontSize: 32,
    marginRight: 14,
  },
  languageInfo: {
    justifyContent: 'center',
  },
  languageName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 2,
  },
  languageNameSelected: {
    color: '#3498db',
  },
  languageCode: {
    fontSize: 12,
    color: '#95a5a6',
    fontWeight: '500',
  },
  radioOuter: {
    width: 22,
    height: 22,
    borderRadius: 11,
    borderWidth: 2,
    borderColor: '#ccc',
    justifyContent: 'center',
    alignItems: 'center',
  },
  radioOuterSelected: {
    borderColor: '#3498db',
  },
  radioInner: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#3498db',
  },
  modalActions: {
    flexDirection: 'row',
    gap: 12,
  },
  cancelButton: {
    flex: 1,
    height: 50,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 12,
    backgroundColor: '#f0f4f8',
  },
  cancelButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#7f8c8d',
  },
  applyButton: {
    flex: 1.5,
    height: 50,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 12,
    backgroundColor: '#3498db',
    gap: 8,
    shadowColor: '#3498db',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 5,
  },
  applyButtonDisabled: {
    backgroundColor: '#85c1e9',
    shadowOpacity: 0.1,
  },
  applyButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: 'white',
  },
});
