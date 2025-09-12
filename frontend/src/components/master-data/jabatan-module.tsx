"use client";

import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { Jabatan } from "@/types";
import { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger, DropdownMenuSeparator } from "@/components/ui/dropdown-menu";
import { DataTable } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

// --- Dialog Formulir ---
const JabatanForm = ({ onSuccess, jabatan, onCancel }: { onSuccess: () => void, jabatan?: Jabatan, onCancel: () => void }) => {
    const [nama, setNama] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        setNama(jabatan?.nama_jabatan || '');
    }, [jabatan]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        const payload = { nama_jabatan: nama };
        try {
            if (jabatan) {
                await api.put(`/jabatan/${jabatan.jabatan_id}`, payload);
                alert("Jabatan berhasil diperbarui.");
            } else {
                await api.post('/jabatan', payload);
                alert("Jabatan baru berhasil ditambahkan.");
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
                <Label htmlFor="nama_jabatan">Nama Jabatan</Label>
                <Input id="nama_jabatan" value={nama} onChange={(e) => setNama(e.target.value)} required disabled={isLoading} />
            </div>
            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onCancel} disabled={isLoading}>Batal</Button>
                <Button type="submit" disabled={isLoading}>{isLoading ? "Menyimpan..." : "Simpan"}</Button>
            </DialogFooter>
        </form>
    );
};

// --- Komponen Utama Modul ---
export function JabatanModule() {
    const [data, setData] = useState<Jabatan[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selected, setSelected] = useState<Jabatan | undefined>(undefined);

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        try {
            const response = await api.get<Jabatan[]>('/jabatan');
            setData(response.data);
        } catch (error) {
            console.error("Gagal mengambil data jabatan:", error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => { fetchData() }, [fetchData]);

    const handleSuccess = () => {
        fetchData();
        setDialogOpen(false);
    };

    const columns: ColumnDef<Jabatan>[] = [
        { accessorKey: "nama_jabatan", header: "Nama Jabatan" },
        { id: "actions", cell: ({ row }) => (
            <DropdownMenu>
                <DropdownMenuTrigger asChild><Button variant="ghost" className="h-8 w-8 p-0"><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuLabel>Aksi</DropdownMenuLabel>
                    <DropdownMenuItem onClick={() => { setSelected(row.original); setDialogOpen(true); }}>Edit</DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        )}
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Daftar Jabatan</CardTitle>
                <CardDescription>Kelola semua jabatan yang ada di perusahaan.</CardDescription>
            </CardHeader>
            <CardContent>
                 <div className="flex justify-end mb-4">
                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild><Button onClick={() => setSelected(undefined)}>Tambah Jabatan</Button></DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{selected ? "Edit Jabatan" : "Tambah Jabatan Baru"}</DialogTitle>
                            </DialogHeader>
                            <JabatanForm onSuccess={handleSuccess} jabatan={selected} onCancel={() => setDialogOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>
                <DataTable columns={columns} data={data} isLoading={isLoading} />
            </CardContent>
        </Card>
    );
}
