import React, { useEffect, useState } from 'react';
import {
  Modal,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';

type Props = {
  visible: boolean;
  onClose: () => void;
  onResult: (code: string) => void;
};

export default function ScannerModal({ visible, onClose, onResult }: Props) {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(false);

  useEffect(() => {
    if (visible) setScanned(false);
  }, [visible]);

  useEffect(() => {
    if (visible && permission && !permission.granted && permission.canAskAgain) {
      requestPermission();
    }
  }, [visible, permission, requestPermission]);

  function handleScan(data: string) {
    if (scanned) return;
    setScanned(true);
    onResult(data);
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.title}>Scanner un QR code</Text>
          <TouchableOpacity onPress={onClose} style={styles.closeBtn}>
            <Text style={styles.closeText}>Fermer</Text>
          </TouchableOpacity>
        </View>

        {!permission ? (
          <View style={styles.center}>
            <Text style={styles.muted}>Initialisation de la caméra…</Text>
          </View>
        ) : !permission.granted ? (
          <View style={styles.center}>
            <Text style={styles.muted}>
              Permission caméra refusée. Autorisez l'accès dans les réglages.
            </Text>
            <TouchableOpacity style={styles.action} onPress={requestPermission}>
              <Text style={styles.actionText}>Demander à nouveau</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <CameraView
            style={styles.camera}
            facing="back"
            barcodeScannerSettings={{
              barcodeTypes: ['qr'],
            }}
            onBarcodeScanned={(e) => handleScan(e.data)}
          >
            <View style={styles.overlay}>
              <View style={styles.frame} />
              <Text style={styles.hint}>Centrez le QR code dans le cadre</Text>
            </View>
          </CameraView>
        )}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 50,
    paddingBottom: 12,
    backgroundColor: '#0f172a',
  },
  title: { color: '#fff', fontSize: 16, fontWeight: '600' },
  closeBtn: { paddingHorizontal: 12, paddingVertical: 6 },
  closeText: { color: '#cbd5e1', fontSize: 14 },
  camera: { flex: 1 },
  overlay: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  frame: {
    width: 250,
    height: 250,
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,0.85)',
    borderRadius: 12,
  },
  hint: { color: '#fff', marginTop: 18, fontSize: 13 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 20 },
  muted: { color: '#94a3b8', textAlign: 'center' },
  action: { marginTop: 16, backgroundColor: '#6366f1', paddingHorizontal: 18, paddingVertical: 10, borderRadius: 8 },
  actionText: { color: '#fff', fontWeight: '600' },
});
