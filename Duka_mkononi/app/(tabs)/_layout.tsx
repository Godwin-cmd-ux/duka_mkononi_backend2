import { Stack } from 'expo-router';
import React from 'react';
import { StatusBar } from 'react-native';
import { useTheme } from '../../lib/theme';

export default function RootLayout() {
  const { colors } = useTheme();

  return (
    <>
      <StatusBar 
        barStyle="dark-content" 
        backgroundColor={colors.card}
      />
      <Stack
        screenOptions={{
          headerStyle: {
            backgroundColor: colors.card,
          },
          headerTintColor: colors.ink,
          headerTitleStyle: {
            fontWeight: 'bold',
          },
          headerShadowVisible: false,
        }}
      >
        <Stack.Screen 
          name="index" 
          options={{ 
            headerShown: false,
            animation: 'fade',
          }} 
        />
        <Stack.Screen 
          name="malipo" 
          options={{ 
            title: 'Malipo',
            headerBackTitle: 'Nyuma',
            animation: 'slide_from_right',
          }} 
        />
      </Stack>
    </>
  );
}
