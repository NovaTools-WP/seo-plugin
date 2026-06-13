import React, { useState, useEffect, useCallback } from "react";
import * as api from "../../api";
import ConnectionStatus from "./ConnectionStatus";
import ConnectButton from "./ConnectButton";
import DisconnectButton from "./DisconnectButton";
import SettingsForm from "./SettingsForm";
import SyncControls from "./SyncControls";
import ProgressBar from "./ProgressBar";
import SyncLog from "./SyncLog";

export default function MerchantCenterPanel() {
  const [connected, setConnected] = useState(false);
  const [reconnectRequired, setReconnectRequired] = useState(false);
  const [email, setEmail] = useState(null);
  const [syncActive, setSyncActive] = useState(false);
  const [syncPaused, setSyncPaused] = useState(false);
  const [loading, setLoading] = useState(true);

  const fetchAuth = useCallback(async () => {
    try {
      const auth = await api.get("/gmc/auth");
      setConnected(auth.connected);
      setReconnectRequired(auth.reconnect_required || false);
      setEmail(auth.email);
    } catch {}
    setLoading(false);
  }, []);

  useEffect(() => {
    fetchAuth();
  }, [fetchAuth]);

  function handleStatusUpdate(status) {
    setSyncActive(status.status === "active");
    setSyncPaused(status.status === "paused");
  }

  function handleDisconnected() {
    setConnected(false);
    setReconnectRequired(false);
    setEmail(null);
  }

  if (loading) {
    return <p className="text-sm text-gray-500">Loading...</p>;
  }

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-6">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">
            Google Merchant Center
          </h3>
          <p className="mt-1 text-sm text-gray-500">
            Sync your WooCommerce products to Google Merchant Center for
            Shopping ads.
          </p>
        </div>
        <ConnectionStatus
          connected={connected}
          email={email}
          reconnectRequired={reconnectRequired}
        />
      </div>

      <div className="mb-6 border-t border-gray-100 pt-4">
        <SettingsForm onSaved={fetchAuth} />
      </div>

      <div className="mb-4 flex items-center gap-4 border-t border-gray-100 pt-4">
        {connected && !reconnectRequired ? (
          <DisconnectButton onDisconnected={handleDisconnected} />
        ) : (
          <ConnectButton onConnected={fetchAuth} />
        )}
        {reconnectRequired && (
          <DisconnectButton onDisconnected={handleDisconnected} />
        )}
      </div>

      {(connected || reconnectRequired) && (
        <div className="space-y-6 border-t border-gray-100 pt-4">
          <SyncControls
            syncActive={syncActive}
            syncPaused={syncPaused}
            onStarted={() => {
              setSyncActive(true);
              setSyncPaused(false);
            }}
            onCancelled={() => {
              setSyncActive(false);
              setSyncPaused(false);
            }}
            onPaused={() => {
              setSyncActive(false);
              setSyncPaused(true);
            }}
            onResumed={() => {
              setSyncActive(true);
              setSyncPaused(false);
            }}
          />
          <ProgressBar
            syncActive={syncActive || syncPaused}
            onStatusUpdate={handleStatusUpdate}
          />
          <SyncLog syncActive={syncActive || syncPaused} />
        </div>
      )}
    </div>
  );
}
