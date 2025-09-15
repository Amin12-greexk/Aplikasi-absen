"use client";

import { useEffect, useState, useCallback } from "react";
import { useAuth } from "@/context/AuthContext";
import { useRouter } from "next/navigation";
import api from "@/lib/api";
import { Karyawan } from "@/types";
import { KaryawanDataTable } from "@/components/karyawan-data-table";
import { columns } from "@/components/karyawan-columns";
import { Button } from "@/components/ui/button";
import { Sidebar } from "@/components/layouts/sidebar";
import { LogOut } from "lucide-react";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

export default function KaryawanPage() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const [data, setData] = useState<Karyawan[]>([]);
  const [dataLoading, setDataLoading] = useState(true);

  const fetchData = useCallback(async () => {
    setDataLoading(true);
    try {
      const response = await api.get<Karyawan[]>("/karyawan");
      setData(response.data);
    } catch (error) {
      console.error("Gagal mengambil data karyawan:", error);
    } finally {
      setDataLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!loading && !user) {
      router.push("/login");
      return;
    }
    if (user) {
      fetchData();
    }
  }, [user, loading, router, fetchData]);

  if (loading || !user) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        Loading...
      </div>
    );
  }

  return (
    <div className="flex min-h-screen bg-gray-100 dark:bg-slate-900">
      {/* Sidebar */}
      <Sidebar />

      {/* Main Content */}
      <div className="flex-1 flex flex-col">
        {/* Header */}
        <header className="bg-white dark:bg-slate-950 shadow-sm p-4">
          <div className="flex justify-end items-center">
            <div className="flex items-center gap-3">
              <div className="text-right">
                <p className="text-sm font-medium text-gray-800 dark:text-gray-200">
                  {user.nama_lengkap}
                </p>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {user.email}
                </p>
              </div>
              <Avatar className="h-9 w-9">
                <AvatarFallback>
                  {user.nama_lengkap?.charAt(0).toUpperCase()}
                </AvatarFallback>
              </Avatar>
              <Button onClick={logout} variant="destructive" size="icon">
                <LogOut className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 p-6">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
            Manajemen Karyawan
          </h1>
          <p className="mt-2 text-gray-600 dark:text-gray-400">
            Kelola semua data karyawan di perusahaan Anda.
          </p>
          <div className="mt-8 bg-white dark:bg-slate-950 shadow rounded-lg p-6">
            <KaryawanDataTable
              columns={columns}
              data={data}
              refetchData={fetchData}
              isLoading={dataLoading}
            />
          </div>
        </main>
      </div>
    </div>
  );
}
