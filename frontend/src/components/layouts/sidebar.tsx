"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/context/AuthContext";
import { cn } from "@/lib/utils";

// Ikon
import { Users, LayoutDashboard, Briefcase, Building, Clock, Wallet } from "lucide-react";

export function Sidebar() {
    const { user } = useAuth();
    const pathname = usePathname();

    // Tentukan peran yang bisa melihat menu manajemen
    const canManageKaryawan = user && ["hr", "direktur", "it_dev"].includes(user.role);
    const canManageMasterData = user && ["hr", "it_dev"].includes(user.role);
    const canManagePengaturanGaji = user && ["hr", "direktur", "it_dev"].includes(user.role);

    return (
        <aside className="w-64 bg-white dark:bg-slate-950 p-6 shadow-md hidden md:block">
            <div className="flex items-center gap-2 mb-10">
                <Briefcase className="h-8 w-8 text-indigo-600" />
                <h1 className="text-2xl font-bold">HRIS</h1>
            </div>
            <nav className="space-y-2">
                {/* Dashboard */}
                <Link
                    href="/"
                    className={cn(
                        "flex items-center gap-3 px-3 py-2 rounded-lg",
                        pathname === "/"
                            ? "bg-indigo-600 text-white"
                            : "text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800"
                    )}
                >
                    <LayoutDashboard className="h-5 w-5" />
                    <span>Dashboard</span>
                </Link>

                {/* Karyawan */}
                {canManageKaryawan && (
                    <Link
                        href="/karyawan"
                        className={cn(
                            "flex items-center gap-3 px-3 py-2 rounded-lg",
                            pathname.startsWith("/karyawan")
                                ? "bg-indigo-600 text-white"
                                : "text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800"
                        )}
                    >
                        <Users className="h-5 w-5" />
                        <span>Karyawan</span>
                    </Link>
                )}

                {/* Master Data */}
                {canManageMasterData && (
                    <Link
                        href="/master-data"
                        className={cn(
                            "flex items-center gap-3 px-3 py-2 rounded-lg",
                            pathname.startsWith("/master-data")
                                ? "bg-indigo-600 text-white"
                                : "text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800"
                        )}
                    >
                        <Building className="h-5 w-5" />
                        <span>Master Data</span>
                    </Link>
                )}

                {/* Pengaturan Gaji */}
                {canManagePengaturanGaji && (
                    <Link
                        href="/pengaturan/gaji"
                        className={cn(
                            "flex items-center gap-3 px-3 py-2 rounded-lg",
                            pathname.startsWith("/pengaturan/gaji")
                                ? "bg-indigo-600 text-white"
                                : "text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800"
                        )}
                    >
                        <Wallet className="h-5 w-5" />
                        <span>Pengaturan Gaji</span>
                    </Link>
                )}
            </nav>
        </aside>
    );
}
