"use client";

import { useAuth } from "@/context/AuthContext";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { Sidebar } from "@/components/layouts/sidebar";
import { Button } from "@/components/ui/button";
import { LogOut } from "lucide-react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { DepartemenModule } from "@/components/master-data/departemen-module";
import { JabatanModule } from "@/components/master-data/jabatan-module"; // <-- Impor
import { ShiftModule } from "@/components/master-data/shift-module"; // <-- Impor

export default function MasterDataPage() {
    const { user, loading, logout } = useAuth();
    const router = useRouter();

    useEffect(() => {
        if (!loading && !user) {
            router.push('/login');
            return;
        }
        if (user && !['hr', 'it_dev'].includes(user.role)) {
            alert("Anda tidak punya hak akses ke halaman ini.");
            router.push('/');
        }
    }, [user, loading, router]);

    if (loading || !user || !['hr', 'it_dev'].includes(user.role)) {
        return <div className="flex items-center justify-center min-h-screen">Loading or Access Denied...</div>;
    }

    return (
        <div className="flex min-h-screen bg-gray-100 dark:bg-slate-900">
            <Sidebar />
            <div className="flex-1 flex flex-col">
                <header className="bg-white dark:bg-slate-950 shadow-sm p-4">
                    <div className="flex justify-end items-center">
                        <div className="mr-4 text-right">
                            <p className="text-sm font-medium text-gray-800 dark:text-gray-200">{user.nama_lengkap}</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">{user.email}</p>
                        </div>
                        <Button onClick={logout} variant="outline" size="icon"><LogOut className="h-4 w-4" /></Button>
                    </div>
                </header>
                <main className="flex-1 p-6">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        Master Data
                    </h1>
                    <p className="mt-2 text-gray-600 dark:text-gray-400">Kelola data referensi untuk sistem HRIS.</p>
                    
                    <Tabs defaultValue="departemen" className="mt-8">
                        <TabsList className="grid w-full grid-cols-3">
                            <TabsTrigger value="departemen">Departemen</TabsTrigger>
                            <TabsTrigger value="jabatan">Jabatan</TabsTrigger>
                            <TabsTrigger value="shift">Shift</TabsTrigger>
                        </TabsList>
                        <TabsContent value="departemen" className="mt-4">
                           <DepartemenModule />
                        </TabsContent>
                        <TabsContent value="jabatan" className="mt-4">
                           <JabatanModule /> {/* <-- Ganti Placeholder */}
                        </TabsContent>
                        <TabsContent value="shift" className="mt-4">
                            <ShiftModule /> {/* <-- Ganti Placeholder */}
                        </TabsContent>
                    </Tabs>
                </main>
            </div>
        </div>
    );
}

