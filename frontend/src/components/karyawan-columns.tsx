"use client"

import { Karyawan } from "@/types"
import { ColumnDef } from "@tanstack/react-table"
import { Badge } from "@/components/ui/badge"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from "@/components/ui/dropdown-menu"
import { Button } from "@/components/ui/button"
import { MoreHorizontal, Pencil, LogOut as ResignIcon } from "lucide-react"
import api from "@/lib/api"
import { KaryawanFormDialog } from "./karyawan-form-dialog"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"

// Fungsi untuk menghapus (mengubah status) karyawan
const deleteKaryawan = async (karyawanId: number, refetch: () => void) => {
    if (confirm("Apakah Anda yakin ingin mengubah status karyawan ini menjadi 'Resign'?")) {
        try {
            await api.delete(`/karyawan/${karyawanId}`);
            alert("Status karyawan berhasil diubah menjadi 'Resign'.");
            refetch();
        } catch (error) {
            console.error("Gagal menghapus karyawan:", error);
            alert("Gagal mengubah status karyawan.");
        }
    }
};

// === Helper untuk badge status ===
const StatusBadge = ({ status }: { status: string }) => {
  let variant: "default" | "destructive" | "secondary" = "default";
  let colorClass = "bg-green-100 text-green-700 border-green-200";

  if (status === "Resign") {
    variant = "destructive";
    colorClass = "bg-red-100 text-red-700 border-red-200";
  } else if (status === "Cuti") {
    variant = "secondary";
    colorClass = "bg-yellow-100 text-yellow-700 border-yellow-200";
  }

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${colorClass}`}
    >
      {status}
    </span>
  );
};

// === Definisi kolom tabel ===
export const columns: ColumnDef<Karyawan>[] = [
  {
    accessorKey: "nama_lengkap",
    header: "Nama",
    cell: ({ row }) => (
      <div className="flex items-center gap-3">
        <Avatar className="h-10 w-10 border shadow-sm">
          <AvatarImage src={`https://api.dicebear.com/8.x/initials/svg?seed=${row.original.nama_lengkap}`} />
          <AvatarFallback>{row.original.nama_lengkap.substring(0, 2).toUpperCase()}</AvatarFallback>
        </Avatar>
        <div>
          <div className="font-semibold text-gray-900 dark:text-gray-100">{row.original.nama_lengkap}</div>
          <div className="text-xs text-gray-500 dark:text-gray-400">{row.original.email}</div>
        </div>
      </div>
    ),
  },
  {
    accessorKey: "jabatan_saat_ini.nama_jabatan",
    header: "Jabatan",
    cell: ({ row }) => (
      <Badge className="bg-blue-100 text-blue-700 border-blue-200">
        {row.original.jabatan_saat_ini?.nama_jabatan || "-"}
      </Badge>
    ),
  },
  {
    accessorKey: "departemen_saat_ini.nama_departemen",
    header: "Departemen",
    cell: ({ row }) => (
      <Badge className="bg-indigo-100 text-indigo-700 border-indigo-200">
        {row.original.departemen_saat_ini?.nama_departemen || "-"}
      </Badge>
    ),
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => <StatusBadge status={row.original.status} />,
  },
  {
    accessorKey: "tanggal_masuk",
    header: "Tanggal Masuk",
    cell: ({ row }) => (
      <span className="text-sm text-gray-600 dark:text-gray-400">
        {new Date(row.original.tanggal_masuk).toLocaleDateString("id-ID", {
          year: "numeric",
          month: "long",
          day: "numeric",
        })}
      </span>
    ),
  },
  {
    id: "actions",
    cell: ({ row, table }) => {
      const karyawan = row.original;
      const { refetchData } = table.options.meta as { refetchData: () => void };

      return (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="h-8 w-8 p-0 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-full">
              <span className="sr-only">Buka menu</span>
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-40">
            <DropdownMenuLabel>Aksi</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <KaryawanFormDialog
              karyawan={karyawan}
              onSuccess={refetchData}
              trigger={
                <DropdownMenuItem onSelect={(e) => e.preventDefault()} className="flex items-center gap-2">
                  <Pencil className="h-4 w-4 text-blue-600" /> Edit Profil
                </DropdownMenuItem>
              }
            />
            <DropdownMenuItem
              onClick={() => deleteKaryawan(karyawan.karyawan_id, refetchData)}
              className="flex items-center gap-2 text-red-600"
            >
              <ResignIcon className="h-4 w-4" /> Ubah Status
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      );
    },
  },
];
