"use client";

import { useEffect, useState, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import api from '@/lib/api';
import { Karyawan, Departemen } from '@/types';

// Komponen
import { Sidebar } from '@/components/layouts/sidebar'; // <-- 1. Pastikan Sidebar diimpor
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";

// Ikon
import { Users, Building, LogOut } from 'lucide-react';


// === Komponen untuk Admin/HR/Direktur ===
const AdminDashboard = () => {
  const [stats, setStats] = useState({ totalKaryawan: 0, totalDepartemen: 0 });
  const [recentKaryawan, setRecentKaryawan] = useState<Karyawan[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchDashboardData = async () => {
      setIsLoading(true);
      try {
        const [karyawanRes, deptRes] = await Promise.all([
          api.get<Karyawan[]>('/karyawan'),
          api.get<Departemen[]>('/departemen')
        ]);
        setStats({
          totalKaryawan: karyawanRes.data.length,
          totalDepartemen: deptRes.data.length,
        });
        setRecentKaryawan(karyawanRes.data.slice(0, 5));
      } catch (error) {
        console.error("Gagal mengambil data dashboard:", error);
      } finally {
        setIsLoading(false);
      }
    };
    fetchDashboardData();
  }, []);

  if (isLoading) {
    return <div>Memuat data dashboard...</div>
  }

  return (
    <>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Total Karyawan</CardTitle>
                    <Users className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.totalKaryawan}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">Total Departemen</CardTitle>
                    <Building className="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold">{stats.totalDepartemen}</div>
                </CardContent>
            </Card>
        </div>
        <div className="mt-8">
            <h2 className="text-xl font-semibold mb-4">Karyawan Baru Bergabung</h2>
            <Card>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nama</TableHead>
                            <TableHead>Jabatan</TableHead>
                            <TableHead>Tanggal Masuk</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {recentKaryawan.map((k) => (
                            <TableRow key={k.karyawan_id}>
                                <TableCell>
                                    <div className="flex items-center gap-3">
                                        <Avatar>
                                            <AvatarImage src={`https://api.dicebear.com/8.x/initials/svg?seed=${k.nama_lengkap}`} />
                                            <AvatarFallback>{k.nama_lengkap.substring(0, 2).toUpperCase()}</AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div className="font-medium">{k.nama_lengkap}</div>
                                            <div className="text-sm text-muted-foreground">{k.email}</div>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>{k.jabatan_saat_ini?.nama_jabatan || '-'}</TableCell>
                                <TableCell>{new Date(k.tanggal_masuk).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </div>
    </>
  );
}

// === Komponen untuk Karyawan Biasa ===
const KaryawanDashboard = () => {
    return (
        <div>
            <Card>
                <CardHeader>
                    <CardTitle>Profil Saya</CardTitle>
                </CardHeader>
                <CardContent>
                    <p>Informasi detail mengenai data pribadi Anda, absensi, dan slip gaji akan tersedia di sini.</p>
                </CardContent>
            </Card>
        </div>
    );
}


export default function DashboardPage() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.push('/login');
    }
  }, [user, loading, router]);

  if (loading || !user) {
    return <div className="flex items-center justify-center min-h-screen">Loading...</div>;
  }
  
  const canViewManagementData = ['hr', 'direktur', 'it_dev'].includes(user.role);

  return (
    <div className="flex min-h-screen bg-gray-100 dark:bg-slate-900">
        
        {/* --- PERUBAHAN DI SINI --- */}
        <Sidebar /> {/* <-- 2. Ganti seluruh blok <aside> dengan komponen ini */}

        <div className="flex-1 flex flex-col">
            <header className="bg-white dark:bg-slate-950 shadow-sm p-4">
                <div className="flex justify-end items-center">
                    <div className="mr-4 text-right">
                        <p className="text-sm font-medium text-gray-800 dark:text-gray-200">{user.nama_lengkap}</p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">{user.email}</p>
                    </div>
                    <Button onClick={logout} variant="outline" size="icon">
                        <LogOut className="h-4 w-4" />
                    </Button>
                </div>
            </header>

            <main className="flex-1 p-6">
                <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                    Selamat Datang, {user.nama_lengkap}!
                </h1>
                <p className="mt-2 text-gray-600 dark:text-gray-400">Anda login sebagai {user.role}.</p>
                <div className="mt-8">
                    {canViewManagementData ? <AdminDashboard /> : <KaryawanDashboard />}
                </div>
            </main>
        </div>
    </div>
  );
}

