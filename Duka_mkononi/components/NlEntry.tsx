// Duka_mkononi/components/NlEntry.tsx
// Capability #25 - Schema-Free Natural-Language Data Entry (seller app panel).
//
// WHAT THIS DOES
// --------------
// One box on the seller's "Other services" hub: describe ONE thing that
// happened ("Nimeuza vipande 3 vya sabuni kwa 2000 kila kimoja") and the app
// asks the server to turn it into an editable preview of exactly one record -
// a product, a sale, an expense or a customer. Nothing is written until the
// seller confirms, and the confirmed record is written by the EXISTING endpoint
// for that type, so the ordinary validation, stock rules and audit trail apply.
//
// WHAT IT REFUSES TO DO
// ---------------------
//  * It never invents a product. If a product reference matches nothing, or
//    matches more than one product, the preview says so and shows the
//    candidates - the seller picks one. An ambiguous match is never chosen for
//    them (the server sends no product id in that case, and this panel cannot
//    invent one).
//  * It never hides a problem: missing required fields, ambiguity flags and a
//    catalogue that could not be read are all rendered from the response.
//  * It never claims success on its own - the confirmation text comes from the
//    existing workflow's own reply.
//
// The record types, the extraction rules and the guard rails all live on the
// server (App\Services\Ai\NlEntry); this panel only renders what it is told.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { API_BASE_URL } from '../constants/api';
import { useLang } from '../context/LanguageContext';
import { fetchWithTimeout } from '../lib/network';

type RecordType = 'product' | 'sale' | 'expense' | 'customer';

interface Candidate {
  product_id: string;
  name: string;
  stock?: number | null;
  selling_price?: number | null;
}

interface Match {
  status: 'matched' | 'ambiguous' | 'not_found';
  product_id: string | null;
  product_name: string | null;
  confidence?: number;
  candidates: Candidate[];
}

interface Flag {
  code: string;
  message: string;
}

interface PreviewResponse {
  status: string;
  type: RecordType;
  type_label: string;
  preview: any;
  resolution: any;
  flags: Flag[];
  missing_required: string[];
  categories: string[];
  units?: Record<string, string>;
  messages?: Record<string, string>;
  examples?: Record<string, string[]>;
  explanation?: string;
}

interface CommitResponse {
  success: boolean;
  committed: boolean;
  duplicate?: boolean;
  type?: string;
  message?: string;
  error?: string;
  code?: string;
  record?: any;
}

const MAX_TEXT = 500;

const UNIT_KEYS = [
  'bottle', 'piece', 'kg', 'litre', 'box', 'packet', 'bag', 'pair',
  'dozen', 'carton', 'roll', 'sheet', 'metre',
];

const PAYMENT_KEYS = ['cash', 'mobile_money', 'bank', 'credit'];

interface FieldSpec {
  key: string;
  label: string;
  kind: 'text' | 'number' | 'select';
  options?: string[];
}

const fieldSpecs = (type: RecordType, categories: string[]): FieldSpec[] => {
  if (type === 'product') {
    return [
      { key: 'name', label: 'name', kind: 'text' },
      { key: 'category', label: 'category', kind: 'text' },
      { key: 'unit', label: 'unit', kind: 'select', options: UNIT_KEYS },
      { key: 'stock', label: 'stock', kind: 'number' },
      { key: 'price', label: 'price', kind: 'number' },
      { key: 'expected_selling_price', label: 'selling_price', kind: 'number' },
      { key: 'description', label: 'description', kind: 'text' },
    ];
  }
  if (type === 'expense') {
    return [
      { key: 'description', label: 'description', kind: 'text' },
      { key: 'amount', label: 'amount', kind: 'number' },
      { key: 'category', label: 'category', kind: 'select', options: categories },
      { key: 'expense_date', label: 'date', kind: 'text' },
    ];
  }
  if (type === 'customer') {
    return [
      { key: 'name', label: 'name', kind: 'text' },
      { key: 'phone', label: 'phone', kind: 'text' },
      { key: 'email', label: 'email', kind: 'text' },
    ];
  }
  return [
    { key: 'quantity', label: 'quantity', kind: 'number' },
    { key: 'unit_price', label: 'unit_price', kind: 'number' },
    { key: 'customer_name', label: 'customer', kind: 'text' },
    { key: 'sale_date', label: 'date', kind: 'text' },
    { key: 'payment_method', label: 'payment', kind: 'select', options: PAYMENT_KEYS },
  ];
};

const newKey = (): string =>
  `entry-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

export default function NlEntry() {
  const { t, lang } = useLang();

  const [open, setOpen] = useState(false);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState('');
  const [noticeKind, setNoticeKind] = useState<'info' | 'warn' | 'error' | 'success'>('info');
  const [response, setResponse] = useState<PreviewResponse | null>(null);
  const [values, setValues] = useState<Record<string, any>>({});
  const [productId, setProductId] = useState<string | null>(null);
  const [missing, setMissing] = useState<string[]>([]);
  const [saved, setSaved] = useState('');
  const [idempotencyKey, setIdempotencyKey] = useState(newKey());
  const [examples, setExamples] = useState<Record<string, string[]> | null>(null);

  const label = useCallback(
    (key: string): string => {
      const local = t(`nl_entry.${key}`);
      return local === `nl_entry.${key}` ? key : local;
    },
    [t],
  );

  const token = useCallback(async (): Promise<string | null> => {
    return AsyncStorage.getItem('userToken');
  }, []);

  const loadExamples = useCallback(async () => {
    try {
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/entry/examples?locale=${encodeURIComponent(lang)}`,
        { headers: { Accept: 'application/json' } },
      );
      const data = await res.json().catch(() => ({}));
      if (res.ok && data?.examples) setExamples(data.examples as Record<string, string[]>);
    } catch (error) {
      // The panel works without examples; they are only a shortcut.
    }
  }, [lang]);

  useEffect(() => {
    if (open && !examples) loadExamples();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, lang]);

  const setField = (key: string, value: any) => {
    setValues((previous) => ({ ...previous, [key]: value }));
  };

  const prepare = async (candidateText?: string) => {
    const body = (candidateText ?? text).trim();
    if (body === '' || busy) return;
    if (candidateText) setText(candidateText);

    setBusy(true);
    setNotice('');
    setSaved('');
    setResponse(null);
    setProductId(null);
    try {
      const bearer = await token();
      if (!bearer) {
        setNoticeKind('error');
        setNotice(label('sign_in'));
        return;
      }
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/entry/preview`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${bearer}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ text: body, locale: lang }),
      });
      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        setNoticeKind('error');
        setNotice(data?.error || label('retry'));
        return;
      }
      if (!data?.preview) {
        setNoticeKind('warn');
        setNotice(data?.explanation || data?.messages?.unsupported || label('unsupported'));
        return;
      }

      const preview = data as PreviewResponse;
      const flat: Record<string, any> = { ...preview.preview };
      const saleItem = (preview.preview?.items || [])[0];
      if (preview.type === 'sale' && saleItem) {
        flat.quantity = saleItem.quantity;
        flat.unit_price = saleItem.unit_price;
      }
      if (preview.type === 'sale') {
        flat.customer_name = preview.preview?.customer_name || '';
        flat.sale_date = preview.preview?.sale_date || '';
        flat.payment_method = preview.preview?.payment_method || 'cash';
      }

      setValues(flat);
      setMissing(preview.missing_required || []);
      setResponse(preview);
      setIdempotencyKey(newKey());

      const item = (preview.resolution?.items || [])[0];
      if (item?.product_id) setProductId(item.product_id);

      if ((preview.missing_required || []).length > 0) {
        setNoticeKind('warn');
        setNotice(label('check_missing'));
      }
    } catch (error) {
      setNoticeKind('error');
      setNotice(label('retry'));
    } finally {
      setBusy(false);
    }
  };

  const commit = async () => {
    if (!response || busy) return;
    if (missing.length > 0) {
      setNoticeKind('warn');
      setNotice(missing.join(', '));
      return;
    }

    setBusy(true);
    setNotice('');
    try {
      const bearer = await token();
      if (!bearer) {
        setNoticeKind('error');
        setNotice(label('sign_in'));
        return;
      }

      const preview = JSON.parse(JSON.stringify(response.preview || {}));
      if (response.type === 'sale') {
        const item = (preview.items || [])[0] || {};
        item.quantity = values.quantity ?? item.quantity;
        item.unit_price = values.unit_price ?? item.unit_price;
        if (productId) item.product_id = productId;
        preview.items = [item];
        preview.customer_name = values.customer_name || preview.customer_name || '';
        preview.sale_date = values.sale_date || preview.sale_date;
        preview.payment_method = values.payment_method || preview.payment_method || 'cash';
      } else {
        Object.keys(values).forEach((key) => {
          preview[key] = values[key];
        });
      }

      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/entry/commit`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${bearer}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          confirm: true,
          type: response.type,
          idempotency_key: idempotencyKey,
          locale: lang,
          preview,
        }),
      });
      const data: CommitResponse = await res.json().catch(() => ({}) as CommitResponse);

      if (res.ok && data?.success) {
        setNoticeKind('success');
        setNotice(data.message || label('saved'));
        setSaved(data.record?.name || data.record?.invoice_number || data.type || 'saved');
        setResponse(null);
        setValues({});
        setMissing([]);
        setText('');
        return;
      }

      setNoticeKind('error');
      setNotice((data as any)?.message || (data as any)?.error || label('retry'));
    } catch (error) {
      setNoticeKind('error');
      setNotice(label('retry'));
    } finally {
      setBusy(false);
    }
  };

  const renderField = (spec: FieldSpec) => {
    const value = values[spec.key];
    const shown = value === null || value === undefined ? '' : String(value);

    if (spec.kind === 'select') {
      const options = spec.options && spec.options.length > 0 ? spec.options : [];
      return (
        <View key={spec.key} style={styles.field}>
          <Text style={styles.fieldLabel}>{label(spec.label)}</Text>
          <View style={styles.chipRow}>
            {options.map((option) => {
              const selected = shown === option;
              return (
                <TouchableOpacity
                  key={option}
                  style={[styles.chip, selected && styles.chipSelected]}
                  onPress={() => setField(spec.key, option)}
                  accessibilityRole="button"
                >
                  <Text style={[styles.chipText, selected && styles.chipTextSelected]}>
                    {option}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </View>
        </View>
      );
    }

    return (
      <View key={spec.key} style={styles.field}>
        <Text style={styles.fieldLabel}>{label(spec.label)}</Text>
        <TextInput
          style={styles.input}
          value={shown}
          keyboardType={spec.kind === 'number' ? 'numeric' : 'default'}
          onChangeText={(next) => setField(spec.key, spec.kind === 'number' ? next.replace(/[^0-9.]/g, '') : next)}
          placeholder={label(spec.label)}
          placeholderTextColor="#94a3b8"
        />
      </View>
    );
  };

  const typeKey = response?.type ? `type_${response.type}` : '';
  const match: Match | null = (response?.resolution?.items || [])[0]?.match || null;

  return (
    <View style={styles.card}>
      <TouchableOpacity
        style={styles.header}
        onPress={() => setOpen((previous) => !previous)}
        accessibilityRole="button"
      >
        <View style={styles.iconWrap}>
          <Ionicons name="create-outline" size={22} color="#fff" />
        </View>
        <View style={styles.headerText}>
          <Text style={styles.title}>{label('title')}</Text>
          <Text style={styles.subtitle}>{label('subtitle')}</Text>
        </View>
        <Ionicons name={open ? 'chevron-up' : 'chevron-down'} size={20} color="#0f7b57" />
      </TouchableOpacity>

      {open && (
        <View style={styles.body}>
          <TextInput
            style={styles.textArea}
            value={text}
            onChangeText={(next) => setText(next.slice(0, MAX_TEXT))}
            placeholder={label('placeholder')}
            placeholderTextColor="#94a3b8"
            multiline
          />
          <Text style={styles.counter}>
            {label('characters_left')} {Math.max(0, MAX_TEXT - text.length)}
          </Text>

          {examples && (
            <View style={styles.chipRow}>
              {(['product', 'sale', 'expense', 'customer'] as RecordType[]).map((type) => {
                const sentence = (examples[type] || [])[0];
                if (!sentence) return null;
                return (
                  <TouchableOpacity
                    key={type}
                    style={styles.exampleChip}
                    onPress={() => prepare(sentence)}
                    accessibilityRole="button"
                  >
                    <Text style={styles.exampleType}>{label(`type_${type}`)}</Text>
                    <Text style={styles.exampleText} numberOfLines={2}>{sentence}</Text>
                  </TouchableOpacity>
                );
              })}
            </View>
          )}

          <TouchableOpacity
            style={[styles.button, styles.buttonPrimary, busy && styles.buttonDisabled]}
            onPress={() => prepare()}
            disabled={busy}
            accessibilityRole="button"
          >
            {busy ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <>
                <Ionicons name="sparkles-outline" size={18} color="#fff" />
                <Text style={styles.buttonPrimaryText}>{label('analyze')}</Text>
              </>
            )}
          </TouchableOpacity>

          {notice !== '' && (
            <View
              style={[
                styles.banner,
                noticeKind === 'error' && styles.bannerError,
                noticeKind === 'warn' && styles.bannerWarn,
                noticeKind === 'success' && styles.bannerSuccess,
              ]}
            >
              <Text style={styles.bannerText}>{notice}</Text>
            </View>
          )}

          {saved !== '' && (
            <Text style={styles.savedLine}>
              <Ionicons name="checkmark-circle" size={14} color="#059669" /> {label('saved')}
            </Text>
          )}

          {response && (
            <View style={styles.preview}>
              <View style={styles.previewHead}>
                <View style={styles.typeBadge}>
                  <Text style={styles.typeBadgeText}>
                    {response.type_label || label(typeKey)}
                  </Text>
                </View>
                {missing.length === 0 ? (
                  <Text style={styles.previewStatus}>{label('preview_title')}</Text>
                ) : (
                  <Text style={styles.previewMissing}>{missing.join(', ')}</Text>
                )}
              </View>

              {match && (
                <View style={styles.matchBox}>
                  <Text style={styles.matchLine}>
                    {match.status === 'matched'
                      ? `${label('matched')}: ${match.product_name || ''}`
                      : match.status === 'ambiguous'
                        ? label('ambiguous')
                        : label('not_found')}
                  </Text>
                  {(match.candidates || []).map((candidate) => {
                    const selected = productId === candidate.product_id;
                    return (
                      <TouchableOpacity
                        key={candidate.product_id}
                        style={[styles.candidate, selected && styles.candidateSelected]}
                        onPress={() => {
                          setProductId(candidate.product_id);
                          setMissing((previous) => {
                            const next = previous.filter((field) => field !== 'items[0].product_id');
                            return next;
                          });
                        }}
                        accessibilityRole="button"
                      >
                        <Text style={[styles.candidateText, selected && styles.candidateTextSelected]}>
                          {candidate.name}
                          {candidate.stock === null || candidate.stock === undefined
                            ? ''
                            : ` · ${candidate.stock}`}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              )}

              {response.resolution?.existing_product && (
                <View style={[styles.banner, styles.bannerInfo]}>
                  <Text style={styles.bannerText}>
                    {label('type_product')}: {response.resolution.existing_product.name}
                  </Text>
                </View>
              )}

              {fieldSpecs(response.type, response.categories || []).map(renderField)}

              {(response.flags || []).map((flag) => (
                <Text key={flag.code} style={styles.flagLine}>
                  {flag.message}
                </Text>
              ))}

              {missing.length > 0 && (
                <Text style={styles.missingLine}>{missing.join(', ')}</Text>
              )}

              {response.resolution?.catalogue?.degraded && (
                <View style={[styles.banner, styles.bannerWarn]}>
                  <Text style={styles.bannerText}>
                    {response.resolution.catalogue.degraded_reason}
                  </Text>
                </View>
              )}

              <View style={styles.actions}>
                <TouchableOpacity
                  style={[
                    styles.button,
                    styles.buttonPrimary,
                    (busy || missing.length > 0) && styles.buttonDisabled,
                  ]}
                  onPress={commit}
                  disabled={busy || missing.length > 0}
                  accessibilityRole="button"
                >
                  <Ionicons name="checkmark-done-outline" size={18} color="#fff" />
                  <Text style={styles.buttonPrimaryText}>{label('confirm')}</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={styles.button}
                  onPress={() => {
                    setResponse(null);
                    setValues({});
                    setMissing([]);
                    setNotice('');
                  }}
                  accessibilityRole="button"
                >
                  <Text style={styles.buttonText}>{label('cancel')}</Text>
                </TouchableOpacity>
              </View>
            </View>
          )}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: 18,
    padding: 16,
    marginTop: 16,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  iconWrap: {
    width: 46,
    height: 46,
    borderRadius: 14,
    backgroundColor: '#0f7b57',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerText: { flex: 1 },
  title: { fontSize: 16, fontWeight: '800', color: '#2c3e50' },
  subtitle: { marginTop: 4, fontSize: 12.5, lineHeight: 18, color: '#5d6d7e' },
  body: { marginTop: 14 },
  textArea: {
    minHeight: 84,
    borderWidth: 1.5,
    borderColor: '#d8dee3',
    borderRadius: 14,
    padding: 12,
    fontSize: 15,
    color: '#2c3e50',
    textAlignVertical: 'top',
  },
  counter: { marginTop: 6, fontSize: 11.5, color: '#94a3b8', textAlign: 'right' },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 10 },
  chip: {
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1.5,
    borderColor: '#d8dee3',
    backgroundColor: '#fff',
  },
  chipSelected: { borderColor: '#0f7b57', backgroundColor: '#e8f8f0' },
  chipText: { fontSize: 12.5, color: '#5d6d7e' },
  chipTextSelected: { color: '#0f7b57', fontWeight: '700' },
  exampleChip: {
    maxWidth: '100%',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e7ebef',
    backgroundColor: '#f4f6f8',
  },
  exampleType: { fontSize: 11, fontWeight: '800', color: '#0f7b57' },
  exampleText: { marginTop: 2, fontSize: 12, color: '#5d6d7e' },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginTop: 12,
    paddingVertical: 12,
    paddingHorizontal: 18,
    borderRadius: 14,
    borderWidth: 1.5,
    borderColor: '#d8dee3',
    backgroundColor: '#fff',
  },
  buttonPrimary: { backgroundColor: '#0f7b57', borderColor: '#0f7b57' },
  buttonPrimaryText: { color: '#fff', fontWeight: '800', fontSize: 14 },
  buttonText: { color: '#2c3e50', fontWeight: '700', fontSize: 14 },
  buttonDisabled: { opacity: 0.55 },
  banner: {
    marginTop: 12,
    padding: 11,
    borderRadius: 12,
    backgroundColor: '#e8f4fd',
  },
  bannerInfo: { backgroundColor: '#e8f4fd' },
  bannerWarn: { backgroundColor: '#fff9e6' },
  bannerError: { backgroundColor: '#fdeaea' },
  bannerSuccess: { backgroundColor: '#f0f8f0' },
  bannerText: { fontSize: 12.5, lineHeight: 18, color: '#2c3e50' },
  savedLine: { marginTop: 10, fontSize: 12.5, color: '#059669', fontWeight: '700' },
  preview: { marginTop: 16, borderTopWidth: 1, borderTopColor: '#ecf0f1', paddingTop: 14 },
  previewHead: { flexDirection: 'row', alignItems: 'center', gap: 10, flexWrap: 'wrap' },
  typeBadge: {
    backgroundColor: '#e8f8f0',
    borderRadius: 20,
    paddingVertical: 5,
    paddingHorizontal: 12,
  },
  typeBadgeText: { color: '#0f7b57', fontWeight: '800', fontSize: 12 },
  previewStatus: { fontSize: 12.5, color: '#7f8c8d' },
  previewMissing: { fontSize: 12.5, color: '#c0392b' },
  matchBox: {
    marginTop: 12,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#eceff3',
    backgroundColor: '#fbfcfe',
  },
  matchLine: { fontSize: 13, color: '#2c3e50' },
  candidate: {
    marginTop: 8,
    paddingVertical: 9,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1.5,
    borderColor: '#d8dee3',
    backgroundColor: '#fff',
  },
  candidateSelected: { borderColor: '#0f7b57', backgroundColor: '#e8f8f0' },
  candidateText: { fontSize: 13, color: '#2c3e50' },
  candidateTextSelected: { fontWeight: '700', color: '#0f7b57' },
  field: { marginTop: 12 },
  fieldLabel: { fontSize: 12, fontWeight: '600', color: '#7f8c8d', marginBottom: 5 },
  input: {
    borderWidth: 1.5,
    borderColor: '#d8dee3',
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
    fontSize: 14,
    color: '#2c3e50',
  },
  flagLine: {
    marginTop: 8,
    fontSize: 12.5,
    color: '#b9770e',
    backgroundColor: '#fff9e6',
    borderRadius: 10,
    padding: 9,
  },
  missingLine: { marginTop: 10, fontSize: 12.5, color: '#c0392b', fontWeight: '600' },
  actions: { flexDirection: 'row', gap: 10, flexWrap: 'wrap' },
});
