import React from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import type { TransactionResponse } from '../api';

type Props =
  | { kind: 'ok'; data: TransactionResponse; onContinue: () => void }
  | { kind: 'error'; message: string; onContinue: () => void };

export default function ResultScreen(props: Props) {
  const ok = props.kind === 'ok';
  return (
    <View style={[styles.container, ok ? styles.bgOk : styles.bgKo]}>
      <View style={styles.card}>
        <Text style={styles.icon}>{ok ? '✓' : '✕'}</Text>
        <Text style={styles.title}>{ok ? 'Transaction validée' : 'Transaction refusée'}</Text>

        {ok ? (
          <View style={styles.detailBlock}>
            <Detail label="Type" value={props.data.transaction.type === 'debit' ? 'Débit' : 'Crédit'} />
            <Detail
              label="Montant"
              value={`${props.data.transaction.amount.toFixed(2)} ${props.data.transaction.currency}`}
            />
            <Detail label="Carte" value={props.data.transaction.card} />
            <Detail label="Intitulé" value={props.data.transaction.label} />
          </View>
        ) : (
          <Text style={styles.errorMsg}>{props.message}</Text>
        )}

        <TouchableOpacity style={styles.button} onPress={props.onContinue}>
          <Text style={styles.buttonText}>Nouvelle transaction</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', padding: 20 },
  bgOk: { backgroundColor: '#064e3b' },
  bgKo: { backgroundColor: '#7f1d1d' },
  card: { backgroundColor: '#1e293b', borderRadius: 16, padding: 24, alignItems: 'center' },
  icon: { fontSize: 56, color: '#fff', marginBottom: 8 },
  title: { color: '#fff', fontSize: 22, fontWeight: '700', marginBottom: 18 },
  detailBlock: { width: '100%', marginTop: 10 },
  detailRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6 },
  detailLabel: { color: '#94a3b8' },
  detailValue: { color: '#fff', fontWeight: '600' },
  errorMsg: { color: '#fecaca', textAlign: 'center', marginTop: 4, lineHeight: 20 },
  button: {
    backgroundColor: '#6366f1',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
    marginTop: 28,
  },
  buttonText: { color: '#fff', fontWeight: '600' },
});
