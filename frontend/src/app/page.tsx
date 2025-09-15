"use client";

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import api from '@/lib/api';
import { Karyawan, Departemen } from '@/types';

// Komponen
import { Sidebar } from '@/components/layouts/sidebar';
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
    return <div className="text-center py-10">Memuat data dashboard...</div>
  }

  return (
    <>
      {/* Statistik ringkas */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <Card className="bg-gradient-to-br from-blue-600 to-blue-400 text-white shadow-lg rounded-2xl">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Karyawan</CardTitle>
            <Users className="h-5 w-5 opacity-80" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{stats.totalKaryawan}</div>
          </CardContent>
        </Card>

        <Card className="bg-gradient-to-br from-yellow-500 to-yellow-400 text-white shadow-lg rounded-2xl">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Departemen</CardTitle>
            <Building className="h-5 w-5 opacity-80" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{stats.totalDepartemen}</div>
          </CardContent>
        </Card>
      </div>

     {/* Karyawan baru */}
<div className="mt-10">
  <h2 className="text-xl font-semibold mb-4 text-gray-800 dark:text-gray-200">
    Karyawan Baru Bergabung
  </h2>
  <Card className="shadow-lg rounded-2xl border border-gray-200 dark:border-slate-800 overflow-hidden">
    <div className="overflow-x-auto">
      <Table className="w-full border-collapse">
        <TableHeader>
          <TableRow className="bg-gradient-to-r from-blue-600 to-green-600 text-white">
            <TableHead className="px-6 py-3 text-left text-sm font-semibold">Nama</TableHead>
            <TableHead className="px-6 py-3 text-left text-sm font-semibold">Jabatan</TableHead>
            <TableHead className="px-6 py-3 text-left text-sm font-semibold">Tanggal Masuk</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {recentKaryawan.map((k, idx) => (
            <TableRow
              key={k.karyawan_id}
              className={`${
                idx % 2 === 0 ? "bg-gray-50 dark:bg-slate-900/50" : "bg-white dark:bg-slate-800"
              } hover:bg-blue-50 dark:hover:bg-slate-700 transition`}
            >
              <TableCell className="px-6 py-4">
                <div className="flex items-center gap-3">
                  <Avatar className="h-10 w-10 border border-gray-300 dark:border-slate-600 shadow-sm">
                    <AvatarImage src={`https://api.dicebear.com/8.x/initials/svg?seed=${k.nama_lengkap}`} />
                    <AvatarFallback>{k.nama_lengkap.substring(0, 2).toUpperCase()}</AvatarFallback>
                  </Avatar>
                  <div>
                    <div className="font-semibold text-gray-900 dark:text-gray-100">
                      {k.nama_lengkap}
                    </div>
                    <div className="text-xs text-gray-500 dark:text-gray-400">{k.email}</div>
                  </div>
                </div>
              </TableCell>
              <TableCell className="px-6 py-4 text-gray-700 dark:text-gray-300">
                {k.jabatan_saat_ini?.nama_jabatan || '-'}
              </TableCell>
              <TableCell className="px-6 py-4 text-gray-600 dark:text-gray-400">
                {new Date(k.tanggal_masuk).toLocaleDateString('id-ID', { 
                  year: 'numeric', 
                  month: 'long', 
                  day: 'numeric' 
                })}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  </Card>
</div>
    </>
  );
}


// === Komponen untuk Karyawan Biasa ===
const KaryawanDashboard = () => {
  return (
    <Card className="shadow-md border border-gray-200 dark:border-slate-800 rounded-2xl">
      <CardHeader className="bg-gradient-to-r from-green-700 to-green-600 text-white rounded-t-2xl">
        <CardTitle>Profil Saya</CardTitle>
      </CardHeader>
      <CardContent className="p-6">
        <p className="text-gray-700 dark:text-gray-300">
          Informasi detail mengenai data pribadi Anda, absensi, dan slip gaji akan tersedia di sini.
        </p>
      </CardContent>
    </Card>
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
    <div className="flex min-h-screen bg-gray-50 dark:bg-slate-900">
      
      {/* Sidebar */}
      <Sidebar />

      <div className="flex-1 flex flex-col">
        
        {/* Header */}
        <header className="bg-white dark:bg-slate-950 shadow-sm p-4 flex justify-between items-center">
          <h1 className="text-lg font-bold text-blue-700 dark:text-blue-400">
            🌿 Tunas Esta HRIS
          </h1>
          <div className="flex items-center gap-4">
            <div className="text-right">
              <p className="text-sm font-medium text-gray-800 dark:text-gray-200">{user.nama_lengkap}</p>
              <p className="text-xs text-gray-500 dark:text-gray-400">{user.email}</p>
            </div>
            <Button onClick={logout} variant="outline" size="icon" className="rounded-full hover:bg-red-50 dark:hover:bg-red-900">
              <LogOut className="h-4 w-4 text-red-600" />
            </Button>
          </div>
        </header>

        {/* Main */}
        <main className="flex-1 p-8">
          <h2 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
            Selamat Datang, {user.nama_lengkap}!
          </h2>
          <p className="mt-2 text-gray-600 dark:text-gray-400">
            Anda login sebagai <span className="font-semibold capitalize">{user.role}</span>.
          </p>

          <div className="mt-10">
            {canViewManagementData ? <AdminDashboard /> : <KaryawanDashboard />}
          </div>
        </main>
      </div>
    </div>
  );
}
