"use client";

import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { Departemen } from "@/types";
import { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from "@/components/ui/dropdown-menu";
import { DataTable } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogClose } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch"; // <-- Sekarang sudah ada
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

// --- Dialog Formulir ---
const DepartemenForm = ({ onSuccess, departemen, onCancel }: { onSuccess: () => void, departemen?: Departemen, onCancel: () => void }) => {
    const [nama, setNama] = useState('');
    const [useShift, setUseShift] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (departemen) {
            setNama(departemen.nama_departemen);
            setUseShift(departemen.menggunakan_shift);
        } else {
            setNama('');
            setUseShift(false);
        }
    }, [departemen]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        const payload = { nama_departemen: nama, menggunakan_shift: useShift };
        try {
            if (departemen) {
                await api.put(`/departemen/${departemen.departemen_id}`, payload);
                alert("Departemen berhasil diperbarui.");
            } else {
                await api.post('/departemen', payload);
                alert("Departemen baru berhasil ditambahkan.");
            }
            onSuccess();
        } catch (error: any) {
            alert(error.response?.data?.message || "Gagal menyimpan data.");
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div>
                <Label htmlFor="nama_departemen">Nama Departemen</Label>
                <Input id="nama_departemen" value={nama} onChange={(e) => setNama(e.target.value)} required disabled={isLoading} />
            </div>
            <div className="flex items-center space-x-2">
                <Switch id="menggunakan_shift" checked={useShift} onCheckedChange={setUseShift} disabled={isLoading} />
                <Label htmlFor="menggunakan_shift">Menggunakan Shift?</Label>
            </div>
            <DialogFooter>
                 <Button type="button" variant="ghost" onClick={onCancel} disabled={isLoading}>Batal</Button>
                 <Button type="submit" disabled={isLoading}>{isLoading ? "Menyimpan..." : "Simpan"}</Button>
            </DialogFooter>
        </form>
    );
};

// --- Komponen Utama Modul ---
export function DepartemenModule() {
    // ... (kode di bawah ini sama persis, tidak perlu diubah)
    const [data, setData] = useState<Departemen[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selected, setSelected] = useState<Departemen | undefined>(undefined);

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        try {
            const response = await api.get<Departemen[]>('/departemen');
            setData(response.data);
        } catch (error) {
            console.error("Gagal mengambil data departemen:", error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const handleSuccess = () => {
        fetchData();
        setDialogOpen(false);
    };

    const columns: ColumnDef<Departemen>[] = [
        { accessorKey: "nama_departemen", header: "Nama Departemen" },
        { accessorKey: "menggunakan_shift", header: "Menggunakan Shift", cell: ({ row }) => row.original.menggunakan_shift ? "Ya" : "Tidak" },
        { accessorKey: "karyawan_count", header: "Jumlah Karyawan" },
        { id: "actions", cell: ({ row }) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild><Button variant="ghost" className="h-8 w-8 p-0"><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Aksi</DropdownMenuLabel>
                    <DropdownMenuItem onClick={() => { setSelected(row.original); setDialogOpen(true); }}>Edit</DropdownMenuItem>
                    {/* Logika Hapus bisa ditambahkan di sini */}
                </DropdownMenuContent>
            </DropdownMenu>
        )}
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Daftar Departemen</CardTitle>
                <CardDescription>Kelola semua departemen yang ada di perusahaan.</CardDescription>
            </CardHeader>
            <CardContent>
                 <div className="flex justify-end mb-4">
                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild>
                            <Button onClick={() => setSelected(undefined)}>Tambah Departemen</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{selected ? "Edit Departemen" : "Tambah Departemen Baru"}</DialogTitle>
                            </DialogHeader>
                            <DepartemenForm onSuccess={handleSuccess} departemen={selected} onCancel={() => setDialogOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>
                <DataTable columns={columns} data={data} isLoading={isLoading} />
            </CardContent>
        </Card>
    );
}
