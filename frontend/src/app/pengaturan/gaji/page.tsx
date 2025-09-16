"use client";

import React, { useState, useEffect } from 'react';
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import api from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

interface SettingGaji {
  [key: string]: any; // Allow dynamic keys
  premi_produksi: number;
  premi_staff: number;
  uang_makan_produksi_weekday: number;
  uang_makan_produksi_weekend_5_10: number;
  uang_makan_produksi_weekend_10_20: number;
  uang_makan_staff_weekday: number;
  uang_makan_staff_weekend_5_10: number;
  uang_makan_staff_weekend_10_20: number;
  tarif_lembur_produksi_per_jam: number;
  tarif_lembur_staff_per_jam: number;
}

const PengaturanGajiPage = () => {
  const { token } = useAuth();
  const [settings, setSettings] = useState<SettingGaji | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    const fetchSettings = async () => {
      if (!token) return;
      try {
        const response = await api.get('/setting-gaji', {
          headers: { Authorization: `Bearer ${token}` }
        });
        setSettings(response.data.data);
      } catch (err) {
        setError('Gagal memuat pengaturan gaji.');
      } finally {
        setLoading(false);
      }
    };
    fetchSettings();
  }, [token]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    if (settings) {
      setSettings({ ...settings, [name]: value });
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!settings || !token) return;

    setSaving(true);
    setError(null);
    setSuccess(null);

    try {
      await api.post('/setting-gaji', settings, {
        headers: { Authorization: `Bearer ${token}` }
      });
      setSuccess('Pengaturan berhasil disimpan!');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Gagal menyimpan pengaturan.');
    } finally {
      setSaving(false);
    }
  };

  const formatLabel = (key: string) => {
    return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  }

  if (loading) return <p>Loading...</p>;

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-2xl font-bold mb-4">Pengaturan Gaji</h1>
       <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>Tarif Gaji Tambahan</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
               {settings && Object.keys(settings).map(key => (
                  // Jangan tampilkan field yang tidak perlu di form
                  !['setting_id', 'is_active', 'created_at', 'updated_at'].includes(key) && (
                     <div className="space-y-2" key={key}>
                      <Label htmlFor={key}>{formatLabel(key)}</Label>
                      <Input
                        id={key}
                        name={key}
                        type="number"
                        value={settings[key]}
                        onChange={handleInputChange}
                        placeholder="Masukkan nominal"
                      />
                    </div>
                  )
               ))}
            </CardContent>
             <CardFooter className="flex-col items-start gap-4">
                {error && <Alert variant="destructive"><AlertDescription>{error}</AlertDescription></Alert>}
                {success && <Alert variant="default" className="bg-green-100 border-green-400 text-green-700"><AlertDescription>{success}</AlertDescription></Alert>}
                <Button type="submit" disabled={saving}>
                  {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                </Button>
            </CardFooter>
          </Card>
       </form>
    </div>
  );
};

export default PengaturanGajiPage;