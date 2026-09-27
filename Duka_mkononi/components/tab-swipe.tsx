import { usePathname, useRouter } from 'expo-router';
import React, { useCallback, useMemo, useRef, useState } from 'react';
import {
  Animated,
  Dimensions,
  Easing,
  LayoutChangeEvent,
  PanResponder,
  PanResponderInstance,
  StyleSheet,
} from 'react-native';

/**
 * Swipe-to-change-tab wrapper with a paper-fold (page-turn) transition.
 *
 * React Navigation's bottom tabs have no built-in swipe, so this wraps a
 * `<Tabs>` navigator, watches horizontal drags and, on a page-turn gesture,
 * folds the current page shut around its spine (a `rotateY` about the leading
 * edge) before unfolding the neighbouring tab in. It deliberately animates the
 * wrapper instead of using the navigator's `animation: 'shift'`, which has a
 * known blank-screen bug when tabs change while react-native-screens is
 * detaching inactive screens.
 *
 * A vertical drag is ignored, so normal scrolling and pull-to-refresh inside a
 * page keep working.
 */
interface TabSwipeProps {
  /** Ordered tab route paths, e.g. ['/muuzaji/profaili', '/muuzaji/mauzo']. */
  tabs: string[];
  children: React.ReactNode;
}

const CLAIM_DISTANCE = 14; // px of horizontal travel before we take the gesture
const SWIPE_DISTANCE = 60; // px needed to commit a page turn on release
const SWIPE_VELOCITY = 0.35; // or a quick flick
const DRAG_SPAN = 0.6; // fraction of the width that equals a fully folded page
const FOLD_ANGLE = 80; // deg at the extremes - near edge-on, never popped away
const FOLD_OUT_MS = 170;
const FOLD_IN_MS = 190;
const PERSPECTIVE = 1100;

/**
 * Index of the tab the given path belongs to.
 *
 * The longest matching tab wins: `/msimamizi/ripoti` matches both
 * `/msimamizi` and `/msimamizi/ripoti`, and picking the first (shortest) one
 * made every msimamizi tab look like the home tab, so swiping only ever worked
 * from home.
 */
function resolveTabIndex(tabs: string[], pathname: string): number {
  let best = -1;
  let bestLength = -1;
  tabs.forEach((tab, index) => {
    const normalised = tab.endsWith('/') ? tab.slice(0, -1) : tab;
    const matches = pathname === normalised || pathname.startsWith(normalised + '/');
    if (matches && normalised.length > bestLength) {
      best = index;
      bestLength = normalised.length;
    }
  });
  return best;
}

export default function TabSwipe({ tabs, children }: TabSwipeProps) {
  const router = useRouter();
  const pathname = usePathname();

  // Single driver: -1 is a fully folded page travelling forwards (next tab),
  // +1 travels backwards, 0 is flat. Two `interpolate`s turn it into the
  // translate, the rotation and the depth shading.
  const drag = useRef(new Animated.Value(0)).current;
  const [width, setWidth] = useState(() => Dimensions.get('window').width);
  // A folded page hinges on the edge leading into the direction of travel.
  const [hinge, setHinge] = useState<'left' | 'right'>('left');
  const hingeRef = useRef<'left' | 'right'>('left');
  const turning = useRef(false);

  const currentIndex = useMemo(() => resolveTabIndex(tabs, pathname), [pathname, tabs]);

  const setHingeOnce = useCallback((next: 'left' | 'right') => {
    if (hingeRef.current !== next) {
      hingeRef.current = next;
      setHinge(next);
    }
  }, []);

  const onLayout = useCallback((event: LayoutChangeEvent) => {
    const next = event.nativeEvent.layout.width;
    if (next > 0) setWidth((current) => (Math.abs(current - next) > 1 ? next : current));
  }, []);

  // The PanResponder is created once, so its callbacks read live values from
  // this ref instead of a stale closure.
  const live = useRef({ currentIndex, router, tabs, width });
  live.current = { currentIndex, router, tabs, width };

  const responder = useRef<PanResponderInstance | null>(null);
  if (!responder.current) {
    const springFlat = () =>
      Animated.timing(drag, {
        toValue: 0,
        duration: 160,
        easing: Easing.out(Easing.quad),
        useNativeDriver: true,
      }).start();

    responder.current = PanResponder.create({
      onStartShouldSetPanResponder: () => false,
      onMoveShouldSetPanResponder: (_evt, gesture) =>
        !turning.current &&
        Math.abs(gesture.dx) > CLAIM_DISTANCE &&
        Math.abs(gesture.dx) > Math.abs(gesture.dy) * 1.5,
      onPanResponderMove: (_evt, gesture) => {
        const { currentIndex: index, tabs: list, width: span } = live.current;
        if (turning.current) return;
        // Resist at either end so the edges feel bounded.
        const atStart = index <= 0 && gesture.dx > 0;
        const atEnd = index >= list.length - 1 && gesture.dx < 0;
        const travelled = (gesture.dx * (atStart || atEnd ? 0.12 : 1)) / (span * DRAG_SPAN);
        const clamped = Math.max(-1, Math.min(1, travelled));
        setHingeOnce(clamped <= 0 ? 'left' : 'right');
        drag.setValue(clamped);
      },
      onPanResponderRelease: (_evt, gesture) => {
        const { currentIndex: index, router: nav, tabs: list } = live.current;
        if (turning.current) return;

        const horizontal = Math.abs(gesture.dx) > Math.abs(gesture.dy);
        const far = Math.abs(gesture.dx) > SWIPE_DISTANCE;
        const fast = Math.abs(gesture.vx) > SWIPE_VELOCITY;

        const goingLeft = gesture.dx < 0 || gesture.vx < -SWIPE_VELOCITY;
        const next = goingLeft ? index + 1 : index - 1;
        const canTurn = horizontal && (far || fast) && index >= 0 && next >= 0 && next < list.length;

        if (!canTurn) {
          springFlat();
          return;
        }

        // Folding forwards hinges on the left spine, backwards on the right.
        const direction = goingLeft ? -1 : 1;
        setHingeOnce(goingLeft ? 'left' : 'right');
        turning.current = true;

        Animated.timing(drag, {
          toValue: direction,
          duration: FOLD_OUT_MS,
          easing: Easing.in(Easing.quad),
          useNativeDriver: true,
        }).start(({ finished }) => {
          if (!finished) {
            turning.current = false;
            springFlat();
            return;
          }
          nav.navigate(list[next] as any);
          // Let the new screen commit, then unfold it flat from the same spine.
          setTimeout(() => {
            drag.setValue(-direction);
            Animated.timing(drag, {
              toValue: 0,
              duration: FOLD_IN_MS,
              easing: Easing.out(Easing.quad),
              useNativeDriver: true,
            }).start(() => {
              turning.current = false;
            });
          }, 16);
        });
      },
      onPanResponderTerminate: () => {
        turning.current = false;
        springFlat();
      },
      onPanResponderTerminationRequest: () => false,
    });
  }

  const translateX = drag.interpolate({
    inputRange: [-1, 1],
    outputRange: [-width * 0.4, width * 0.4],
  });
  const rotateY = drag.interpolate({
    inputRange: [-1, 1],
    outputRange: [`-${FOLD_ANGLE}deg`, `${FOLD_ANGLE}deg`],
  });
  const opacity = drag.interpolate({
    inputRange: [-1, 0, 1],
    outputRange: [0.72, 1, 0.72],
  });

  return (
    <Animated.View
      onLayout={onLayout}
      style={[
        styles.container,
        { transformOrigin: [hinge === 'left' ? 0 : width, 0, 0] },
        { opacity, transform: [{ perspective: PERSPECTIVE }, { translateX }, { rotateY }] },
      ]}
      {...responder.current.panHandlers}
    >
      {children}
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
});
