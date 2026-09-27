import { Ionicons } from '@expo/vector-icons';
import React, { useState } from 'react';
import {
  Image,
  ImageResizeMode,
  ImageStyle,
  Modal,
  StyleProp,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
  ViewStyle,
} from 'react-native';

/**
 * Tap-to-zoom image, the mobile counterpart of the web
 * `partials/photo-viewer.blade.php` lightbox. Renders the normal inline image
 * and opens a full-screen viewer on tap, so every profile / business photo in
 * the app behaves the same way it does on the website.
 */
interface ZoomableImageProps {
  uri?: string | null;
  /** Style of the inline image (avatar / logo size, radius, ...). */
  style?: StyleProp<ImageStyle>;
  /** Extra style for the touchable wrapper around the inline image. */
  wrapStyle?: StyleProp<ViewStyle>;
  /** Caption shown on the full-screen viewer. */
  name?: string;
  resizeMode?: ImageResizeMode;
}

export default function ZoomableImage({
  uri,
  style,
  wrapStyle,
  name,
  resizeMode = 'cover',
}: ZoomableImageProps) {
  const [visible, setVisible] = useState(false);

  if (!uri || String(uri).trim() === '') return null;

  return (
    <>
      <TouchableOpacity
        activeOpacity={0.85}
        onPress={() => setVisible(true)}
        style={wrapStyle}
        accessibilityRole="imagebutton"
      >
        <Image source={{ uri }} style={style} resizeMode={resizeMode} />
      </TouchableOpacity>

      <Modal
        visible={visible}
        transparent
        animationType="fade"
        onRequestClose={() => setVisible(false)}
        statusBarTranslucent
      >
        <View style={styles.overlay}>
          <TouchableOpacity
            style={styles.close}
            onPress={() => setVisible(false)}
            accessibilityRole="button"
          >
            <Ionicons name="close" size={26} color="#ffffff" />
          </TouchableOpacity>

          {name ? (
            <Text style={styles.name} numberOfLines={1}>
              {name}
            </Text>
          ) : null}

          <TouchableOpacity
            style={styles.stage}
            activeOpacity={1}
            onPress={() => setVisible(false)}
          >
            <Image source={{ uri }} style={styles.fullImage} resizeMode="contain" />
          </TouchableOpacity>
        </View>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.94)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  close: {
    position: 'absolute',
    top: 44,
    right: 20,
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: 'rgba(255, 255, 255, 0.18)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 2,
  },
  name: {
    position: 'absolute',
    top: 52,
    left: 20,
    right: 74,
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
    zIndex: 2,
  },
  stage: {
    width: '100%',
    height: '100%',
    alignItems: 'center',
    justifyContent: 'center',
  },
  fullImage: {
    width: '92%',
    height: '80%',
  },
});
