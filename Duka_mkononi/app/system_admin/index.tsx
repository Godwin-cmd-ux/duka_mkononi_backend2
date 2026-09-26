// C:\Users\HP\myApp\Duka_mkononi\app\system_admin\index.tsx
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';
import { Redirect } from 'expo-router';
import { View, Text, ActivityIndicator } from 'react-native';
import { useEffect, useState } from 'react';
import { useLang } from '../../context/LanguageContext';

export default function SystemAdminIndex() {
  const { t } = useLang();
  return <Redirect href="/system_admin/dashboard" />;
}