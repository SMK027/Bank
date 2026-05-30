import React, { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import LoginScreen from './src/screens/LoginScreen';
import TransactionScreen from './src/screens/TransactionScreen';
import ResultScreen from './src/screens/ResultScreen';
import { tokenStore } from './src/tokenStore';
import { api } from './src/api';
import type { PosStatus, TransactionResponse, User } from './src/api';

type Session = { token: string; user: User; pos: PosStatus };
type Result = { kind: 'ok'; data: TransactionResponse } | { kind: 'error'; message: string };

export default function App() {
  const [booting, setBooting] = useState(true);
  const [session, setSession] = useState<Session | null>(null);
  const [result, setResult] = useState<Result | null>(null);

  useEffect(() => {
    (async () => {
      const token = await tokenStore.get();
      if (!token) {
        setBooting(false);
        return;
      }
      try {
        const status = await api.status(token);
        setSession({ token, user: status.user, pos: status.pos });
      } catch {
        await tokenStore.clear();
      } finally {
        setBooting(false);
      }
    })();
  }, []);

  async function handleLogout() {
    await tokenStore.clear();
    setSession(null);
    setResult(null);
  }

  if (booting) {
    return (
      <View style={styles.boot}>
        <StatusBar style="light" />
        <ActivityIndicator color="#fff" size="large" />
      </View>
    );
  }

  if (!session) {
    return (
      <>
        <StatusBar style="light" />
        <LoginScreen
          onSuccess={async (data) => {
            await tokenStore.set(data.token);
            setSession({ token: data.token, user: data.user, pos: data.pos });
          }}
        />
      </>
    );
  }

  if (result) {
    return (
      <>
        <StatusBar style="light" />
        <ResultScreen
          {...result}
          onContinue={async () => {
            // rafraîchir le statut TPE après chaque transaction
            try {
              const refreshed = await api.status(session.token);
              setSession({ ...session, user: refreshed.user, pos: refreshed.pos });
            } catch {
              // si le token a expiré, on déconnecte
              await handleLogout();
            }
            setResult(null);
          }}
        />
      </>
    );
  }

  return (
    <>
      <StatusBar style="light" />
      <TransactionScreen
        token={session.token}
        user={session.user}
        pos={session.pos}
        onResult={setResult}
        onLogout={handleLogout}
      />
    </>
  );
}

const styles = StyleSheet.create({
  boot: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#0f172a' },
});
