import React, { useEffect, useMemo, useState } from 'react';
import {
  AccessibilityInfo,
  Animated,
  StyleProp,
  StyleSheet,
  Text,
  View,
  ViewStyle,
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';

/**
 * DUKA MKONONI — SMARTCART
 * ------------------------
 * The signature loading indicator of the whole app. One component, one
 * animation, three presentations:
 *
 *   compact   20px   buttons, chips and other inline busy states
 *   standard  48px   section / card loading states
 *   large     68px   full page loading states (with the brand line)
 *
 * The cycle (2.15s, loops):
 *   0–12%   the cart is drawn in
 *  13–20%   the product appears above the basket
 *  20–40%   the product drops into the basket
 *  39–45%   the basket dips and settles
 *  50–70%   the smart pulse travels across the cart
 *  74–84%   the cart rolls forward and settles back
 *  84–100%  the signature pause
 *
 * Nothing here paints a background: the screen keeps its own colours exactly
 * as they are. Everything is opacity + transform driven by a single native
 * driver, so it stays smooth on low end phones and it stops by unmounting —
 * there is no timer and no polling.
 *
 * `prefers-reduced-motion` (AccessibilityInfo.isReduceMotionEnabled) keeps the
 * cart on screen and removes all movement.
 */

export type SmartCartSize = 'compact' | 'standard' | 'large';

export interface SmartCartLoaderProps {
  /** Presentation. A number is also accepted and treated as a box size in dp. */
  size?: SmartCartSize | number;
  /** Cart colour. Defaults to the DukaMkononi primary; pass "white" on dark or
   *  coloured surfaces so the cart stays legible. */
  color?: string;
  /** Product (package) colour. */
  accent?: string;
  /** Accessible loading message announced to screen readers. */
  label?: string;
  /** Draw the "DUKA MKONONI" line under the cart (used by full page loaders). */
  brand?: boolean;
  style?: StyleProp<ViewStyle>;
}

const CYCLE_MS = 2150;
const BRAND_NAVY = '#1e3a8a';
const BRAND_AMBER = '#f59e0b';

const BOX: Record<SmartCartSize, number> = {
  compact: 20,
  standard: 48,
  large: 68,
};

/** Lighten a #rrggbb colour towards white so the pulse reads as light. */
function lighten(hex: string, amount: number): string {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return hex;
  const n = parseInt(m[1], 16);
  const mix = (c: number) => Math.round(c + (255 - c) * amount);
  const r = mix((n >> 16) & 255);
  const g = mix((n >> 8) & 255);
  const b = mix(n & 255);
  return `#${((r << 16) | (g << 8) | b).toString(16).padStart(6, '0')}`;
}

export default function SmartCartLoader({
  size = 'standard',
  color = BRAND_NAVY,
  accent = BRAND_AMBER,
  label = 'Loading',
  brand = false,
  style,
}: SmartCartLoaderProps) {
  const box = typeof size === 'number' ? Math.max(12, size) : BOX[size];
  const withProduct = box >= 28;
  const pulseWidth = Math.round(box * 0.42);

  const [reducedMotion, setReducedMotion] = useState(false);
  useEffect(() => {
    let mounted = true;
    AccessibilityInfo.isReduceMotionEnabled()
      .then((enabled) => {
        if (mounted) setReducedMotion(!!enabled);
      })
      .catch(() => {});
    return () => {
      mounted = false;
    };
  }, []);

  // One value drives the whole cycle; every layer interpolates from it.
  const cycle = useMemo(() => new Animated.Value(0), []);

  useEffect(() => {
    if (reducedMotion) return undefined;
    const loop = Animated.loop(
      Animated.timing(cycle, {
        toValue: 1,
        duration: CYCLE_MS,
        useNativeDriver: true,
      }),
    );
    loop.start();
    return () => loop.stop();
  }, [cycle, reducedMotion]);

  // ---- the cart ----------------------------------------------------------
  const cartOpacity = cycle.interpolate({
    inputRange: [0, 0.12, 1],
    outputRange: [0, 1, 1],
  });
  const cartDip = cycle.interpolate({
    inputRange: [0, 0.39, 0.45, 1],
    outputRange: [0, box * 0.055, 0, 0],
  });
  const cartRoll = cycle.interpolate({
    inputRange: [0, 0.74, 0.84, 1],
    outputRange: [0, box * 0.07, 0, 0],
  });

  // ---- the product -------------------------------------------------------
  const productOpacity = cycle.interpolate({
    inputRange: [0, 0.13, 0.2, 0.4, 0.45, 1],
    outputRange: [0, 0, 1, 1, 0, 0],
  });
  const productScale = cycle.interpolate({
    inputRange: [0, 0.13, 0.2, 1],
    outputRange: [0.72, 0.72, 1, 1],
  });
  // 245% of the package height, mirroring the web animation.
  const productDrop = Math.round(box * 0.16 * 2.45);

  // ---- the smart pulse ---------------------------------------------------
  const pulseOpacity = cycle.interpolate({
    inputRange: [0, 0.48, 0.5, 0.7, 0.72, 1],
    outputRange: [0, 0, 1, 1, 0, 0],
  });
  const bandX = cycle.interpolate({
    inputRange: [0, 0.5, 0.7, 1],
    outputRange: [-pulseWidth, -pulseWidth, box, box],
  });
  const bandIconX = cycle.interpolate({
    inputRange: [0, 0.5, 0.7, 1],
    outputRange: [pulseWidth, -box, -box],
  });

  const bright = lighten(color, 0.55);

  return (
    <View
      style={[styles.root, style]}
      accessible
      accessibilityRole="progressbar"
      accessibilityLabel={label}
    >
      <View style={[styles.stage, { width: box, height: box }]}>
        {/* the trolley */}
        <Animated.View
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            width: box,
            height: box,
            opacity: reducedMotion ? 1 : cartOpacity,
            transform: reducedMotion
              ? []
              : [{ translateY: cartDip }, { translateX: cartRoll }],
          }}
        >
          <MaterialCommunityIcons name="cart-outline" size={box} color={color} />
        </Animated.View>

        {/* the smart pulse: a narrow window that sweeps a brighter copy of the
            cart across its own outline, so the light belongs to the cart */}
        {!reducedMotion && (
          <Animated.View
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              width: pulseWidth,
              height: box,
              overflow: 'hidden',
              opacity: pulseOpacity,
              transform: [{ translateX: bandX }],
            }}
          >
            {/* counter-translated so the bright cart stays perfectly aligned
                with the base cart underneath the moving window */}
            <Animated.View
              style={{
                position: 'absolute',
                top: 0,
                left: 0,
                width: box,
                height: box,
                transform: [{ translateX: bandIconX }],
              }}
            >
              <MaterialCommunityIcons name="cart-outline" size={box} color={bright} />
            </Animated.View>
          </Animated.View>
        )}

        {/* the product dropping into the basket */}
        {withProduct && !reducedMotion && (
          <Animated.View
            style={{
              position: 'absolute',
              top: Math.round(box * 0.03),
              left: Math.round(box * 0.42),
              width: Math.round(box * 0.2),
              height: Math.round(box * 0.16),
              borderRadius: Math.max(1, Math.round(box * 0.035)),
              backgroundColor: accent,
              opacity: productOpacity,
              transform: [{ translateY: productDrop }, { scale: productScale }],
            }}
          />
        )}
      </View>

      {brand && (
        <Text style={styles.brand} numberOfLines={1}>
          DUKA MKONONI
        </Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  stage: {
    position: 'relative',
  },
  brand: {
    marginTop: 10,
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 2,
    color: '#64748b',
    textTransform: 'uppercase',
  },
});
