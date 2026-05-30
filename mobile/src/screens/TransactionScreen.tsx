import React, { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { api } from '../api';
import type { PosStatus, StatusResponse, TransactionResponse, User } from '../api';
import ScannerModal from '../components/ScannerModal';

type Props = {
  token: string;
  user: User;
  pos: PosStatus;
  onResult: (result: { kind: 'ok'; data: TransactionResponse } | { kind: 'error'; message: string }) => void;
  onLogout: () => void;
  onSessionRefresh?: (status: StatusResponse) => void;
};

type TxType = 'debit' | 'credit';

function formatCard(raw: string): string {
  const digits = raw.replace(/\D/g, '').slice(0, 19);
  return digits.replace(/(.{4})/g, '$1 ').trim();
}

export default function TransactionScreen({ token, user, pos, onResult, onLogout, onSessionRefresh }: Props) {
  const [type, setType] = useState<TxType>('debit');
  const [card, setCard] = useState('');
  const [label, setLabel] = useState('');
  const [amount, setAmount] = useState('');
  // 0 = aucun crédit (autorisé uniquement pour un débit).
  const [accountId, setAccountId] = useState<number>(0);
  const [scannerOpen, setScannerOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function changeType(next: TxType) {
    setType(next);
    if (next === 'credit' && accountId === 0) {
      const first = pos.accounts[0]?.id;
      if (first) setAccountId(first);
    }
  }

  // Rafraîchit périodiquement le statut TPE pour détecter un ban / une
  // désactivation appliquée par la modération pendant que l'écran est
  // ouvert. Le composant parent passe au BlockedView si can_operate=false.
  useEffect(() => {
    if (!onSessionRefresh) return;
    let cancelled = false;
    const refresh = async () => {
      try {
        const s = await api.status(token);
        if (!cancelled) onSessionRefresh(s);
      } catch {
        /* silencieux */
      }
    };
    const id = setInterval(refresh, 20000);
    return () => { cancelled = true; clearInterval(id); };
  }, [token, onSessionRefresh]);

  const canSubmit = useMemo(
    () =>
      card.replace(/\D/g, '').length >= 13 &&
      label.trim() !== '' &&
      parseFloat(amount.replace(',', '.')) > 0 &&
      (type === 'debit' || accountId > 0),
    [card, label, amount, type, accountId]
  );

  if (!pos.active) {
    return (
      <BlockedView
        title="TPE désactivé"
        message={`Le TPE est actuellement désactivé par la modération.${pos.reason ? ` Motif : ${pos.reason}.` : ''}`}
        onLogout={onLogout}
      />
    );
  }
  if (pos.banned) {
    return (
      <BlockedView
        title="Accès TPE suspendu"
        message={`Votre accès au TPE est suspendu par la modération.${pos.ban_reason ? ` Motif : ${pos.ban_reason}.` : ''}`}
        onLogout={onLogout}
      />
    );
  }
  if (!pos.can_operate) {
    return (
      <BlockedView
        title="TPE indisponible"
        message="Le TPE n'est pas disponible pour le moment."
        onLogout={onLogout}
      />
    );
  }

  async function submit() {
    setSubmitting(true);
    setError(null);
    try {
      // Vérifie en temps réel que le TPE n'a pas été désactivé / l'utilisateur banni
      // depuis l'ouverture de l'écran, sinon la modération pourrait être
      // contournée par une session mise en cache.
      try {
        const fresh = await api.status(token);
        if (onSessionRefresh) onSessionRefresh(fresh);
        if (!fresh.pos.active || fresh.pos.banned || !fresh.pos.can_operate) {
          setSubmitting(false);
          return; // le parent affichera BlockedView au prochain rendu
        }
        if (accountId > 0 && !fresh.pos.accounts.some((a) => a.id === accountId)) {
          setAccountId(0);
          setError("Le compte d'encaissement sélectionné n'est plus disponible.");
          setSubmitting(false);
          return;
        }
      } catch {
        /* on tente quand même la transaction si le ping échoue */
      }
      const result = await api.transaction(token, {
        type,
        card_number: card.replace(/\s/g, ''),
        label: label.trim(),
        amount: parseFloat(amount.replace(',', '.')),
        account_id: accountId,
      });
      onResult({ kind: 'ok', data: result });
    } catch (e: any) {
      setError(e?.message ?? 'Erreur lors de la transaction.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView contentContainerStyle={styles.scroll}>
        <View style={styles.headerRow}>
          <View>
            <Text style={styles.welcome}>{user.first_name || user.email}</Text>
            <Text style={styles.role}>
              {user.is_moderator ? 'Modérateur' : user.company ?? 'Compte professionnel'}
            </Text>
          </View>
          <TouchableOpacity onPress={onLogout}>
            <Text style={styles.logout}>Déconnexion</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Type d'opération</Text>
          <View style={styles.segment}>
            <SegmentButton label="Débit" active={type === 'debit'} onPress={() => changeType('debit')} />
            <SegmentButton
              label="Crédit"
              active={type === 'credit'}
              onPress={() => changeType('credit')}
              disabled={pos.accounts.length === 0}
            />
          </View>

          <Text style={styles.label}>Numéro de carte</Text>
          <View style={styles.row}>
            <TextInput
              style={[styles.input, { flex: 1 }]}
              value={card}
              onChangeText={(v) => setCard(formatCard(v))}
              keyboardType="number-pad"
              placeholder="4242 4242 4242 4242"
              placeholderTextColor="#94a3b8"
              maxLength={23}
            />
            <TouchableOpacity style={styles.scanBtn} onPress={() => setScannerOpen(true)}>
              <Text style={styles.scanBtnText}>Scanner</Text>
            </TouchableOpacity>
          </View>

          <Text style={styles.label}>Intitulé</Text>
          <TextInput
            style={styles.input}
            value={label}
            onChangeText={setLabel}
            placeholder="Achat menu midi"
            placeholderTextColor="#94a3b8"
            maxLength={120}
          />

          <Text style={styles.label}>Montant</Text>
          <TextInput
            style={styles.input}
            value={amount}
            onChangeText={setAmount}
            keyboardType="decimal-pad"
            placeholder="0,00"
            placeholderTextColor="#94a3b8"
          />

          {pos.accounts.length > 0 && (
            <>
              <Text style={styles.label}>
                {type === 'debit' ? 'Compte à créditer (facultatif)' : 'Compte d\'encaissement'}
              </Text>
              <View style={styles.accountList}>
                {type === 'debit' && (
                  <TouchableOpacity
                    key="none"
                    style={[styles.accountChip, accountId === 0 && styles.accountChipActive]}
                    onPress={() => setAccountId(0)}
                  >
                    <Text style={[styles.accountText, accountId === 0 && styles.accountTextActive]}>
                      Aucun crédit
                    </Text>
                  </TouchableOpacity>
                )}
                {pos.accounts.map((a) => (
                  <TouchableOpacity
                    key={a.id}
                    style={[styles.accountChip, accountId === a.id && styles.accountChipActive]}
                    onPress={() => setAccountId(a.id)}
                  >
                    <Text style={[styles.accountText, accountId === a.id && styles.accountTextActive]}>
                      {a.label} ({a.currency}){a.shared ? ' • partagé' : ''}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </>
          )}

          {error && <Text style={styles.error}>{error}</Text>}

          <TouchableOpacity
            style={[styles.submit, (!canSubmit || submitting) && styles.submitDisabled]}
            onPress={submit}
            disabled={!canSubmit || submitting}
          >
            {submitting ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.submitText}>
                Valider {type === 'debit' ? 'le débit' : 'le crédit'}
              </Text>
            )}
          </TouchableOpacity>
        </View>
      </ScrollView>

      <ScannerModal
        visible={scannerOpen}
        onClose={() => setScannerOpen(false)}
        onResult={(data) => {
          setScannerOpen(false);
          setCard(formatCard(data));
        }}
      />
    </KeyboardAvoidingView>
  );
}

function SegmentButton({ label, active, onPress, disabled }: { label: string; active: boolean; onPress: () => void; disabled?: boolean }) {
  return (
    <TouchableOpacity
      style={[styles.segmentBtn, active && styles.segmentBtnActive, disabled && styles.segmentBtnDisabled]}
      onPress={onPress}
      disabled={disabled}
    >
      <Text style={[styles.segmentText, active && styles.segmentTextActive, disabled && styles.segmentTextDisabled]}>{label}</Text>
    </TouchableOpacity>
  );
}

function BlockedView({ title, message, onLogout }: { title: string; message: string; onLogout: () => void }) {
  return (
    <View style={[styles.container, { justifyContent: 'center', padding: 24 }]}>
      <View style={styles.card}>
        <Text style={[styles.sectionTitle, { color: '#fca5a5' }]}>{title}</Text>
        <Text style={{ color: '#cbd5e1', marginTop: 8, lineHeight: 20 }}>{message}</Text>
        <TouchableOpacity style={[styles.submit, { marginTop: 20 }]} onPress={onLogout}>
          <Text style={styles.submitText}>Se déconnecter</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0f172a' },
  scroll: { padding: 16, paddingTop: 48 },
  headerRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 },
  welcome: { color: '#fff', fontSize: 18, fontWeight: '600' },
  role: { color: '#94a3b8', fontSize: 12 },
  logout: { color: '#6366f1', fontSize: 13 },
  card: { backgroundColor: '#1e293b', padding: 20, borderRadius: 16 },
  sectionTitle: { color: '#fff', fontSize: 16, fontWeight: '600' },
  segment: { flexDirection: 'row', marginTop: 10, marginBottom: 4, gap: 8 },
  segmentBtn: {
    flex: 1, paddingVertical: 10, borderRadius: 8, alignItems: 'center',
    backgroundColor: '#0f172a', borderWidth: 1, borderColor: '#334155',
  },
  segmentBtnActive: { backgroundColor: '#6366f1', borderColor: '#6366f1' },
  segmentBtnDisabled: { opacity: 0.4 },
  segmentText: { color: '#cbd5e1', fontWeight: '500' },
  segmentTextActive: { color: '#fff' },
  segmentTextDisabled: { color: '#64748b' },
  label: { color: '#cbd5e1', marginTop: 14, marginBottom: 6, fontSize: 13 },
  input: {
    backgroundColor: '#0f172a', color: '#fff',
    borderWidth: 1, borderColor: '#334155', borderRadius: 8,
    paddingHorizontal: 12, paddingVertical: 10,
  },
  row: { flexDirection: 'row', gap: 8 },
  scanBtn: {
    backgroundColor: '#334155', paddingHorizontal: 14, justifyContent: 'center', borderRadius: 8,
  },
  scanBtnText: { color: '#fff', fontWeight: '600' },
  accountList: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  accountChip: {
    backgroundColor: '#0f172a', borderWidth: 1, borderColor: '#334155',
    paddingHorizontal: 12, paddingVertical: 8, borderRadius: 999,
  },
  accountChipActive: { backgroundColor: '#6366f1', borderColor: '#6366f1' },
  accountText: { color: '#cbd5e1', fontSize: 13 },
  accountTextActive: { color: '#fff' },
  error: { color: '#fca5a5', marginTop: 14 },
  submit: {
    backgroundColor: '#22c55e', paddingVertical: 14, borderRadius: 8,
    alignItems: 'center', marginTop: 20,
  },
  submitDisabled: { opacity: 0.5 },
  submitText: { color: '#fff', fontWeight: '700', fontSize: 16 },
});
