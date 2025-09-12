"use client"

import { Karyawan } from "@/types"
import { ColumnDef } from "@tanstack/react-table"
import { Badge } from "@/components/ui/badge"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from "@/components/ui/dropdown-menu"
import { Button } from "@/components/ui/button"
import { MoreHorizontal } from "lucide-react"
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
}

// Ini adalah tempat Anda mendefinisikan setiap kolom pada tabel
export const columns: ColumnDef<Karyawan>[] = [
  {
    accessorKey: "nama_lengkap",
    header: "Nama",
    cell: ({ row }) => (
        <div className="flex items-center gap-3">
            <Avatar>
                <AvatarImage src={`https://api.dicebear.com/8.x/initials/svg?seed=${row.original.nama_lengkap}`} />
                <AvatarFallback>{row.original.nama_lengkap.substring(0, 2).toUpperCase()}</AvatarFallback>
            </Avatar>
            <div>
                <div className="font-medium">{row.original.nama_lengkap}</div>
                <div className="text-sm text-muted-foreground">{row.original.email}</div>
            </div>
        </div>
    ),
  },
  {
    accessorKey: "jabatan_saat_ini.nama_jabatan",
    header: "Jabatan",
  },
  {
    accessorKey: "departemen_saat_ini.nama_departemen",
    header: "Departemen",
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => (
      <Badge variant={row.original.status === 'Aktif' ? 'default' : 'destructive'}>
        {row.original.status}
      </Badge>
    ),
  },
   {
    accessorKey: "tanggal_masuk",
    header: "Tanggal Masuk",
     cell: ({ row }) => new Date(row.original.tanggal_masuk).toLocaleDateString('id-ID', {
        year: 'numeric', month: 'long', day: 'numeric'
    }),
  },
  {
    id: "actions",
    cell: ({ row, table }) => {
      const karyawan = row.original;
      const { refetchData } = table.options.meta as { refetchData: () => void };

      return (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="h-8 w-8 p-0">
              <span className="sr-only">Buka menu</span>
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>Aksi</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <KaryawanFormDialog 
                karyawan={karyawan} 
                onSuccess={refetchData}
                trigger={<DropdownMenuItem onSelect={(e) => e.preventDefault()}>Edit Profil</DropdownMenuItem>} 
            />
            <DropdownMenuItem onClick={() => deleteKaryawan(karyawan.karyawan_id, refetchData)}>
              Ubah Status (Resign)
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      )
    },
  },
]

