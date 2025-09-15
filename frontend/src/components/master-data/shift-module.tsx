"use client";

import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { Shift } from "@/types";
import { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { DataTable } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

// --- Dialog Formulir ---
const ShiftForm = ({ onSuccess, shift, onCancel }: { onSuccess: () => void, shift?: Shift, onCancel: () => void }) => {
    // --- PERUBAHAN DI SINI ---
    // Berikan nilai default yang valid untuk jam
    const [formData, setFormData] = useState({
        kode_shift: '', jam_masuk: '08:00', jam_pulang: '17:00', hari_berikutnya: false
    });
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (shift) {
            setFormData({
                kode_shift: shift.kode_shift,
                jam_masuk: shift.jam_masuk ? shift.jam_masuk.substring(0, 5) : '00:00',
                jam_pulang: shift.jam_pulang ? shift.jam_pulang.substring(0, 5) : '00:00',
                hari_berikutnya: shift.hari_berikutnya,
            });
        } else {
            // Reset ke nilai default yang valid saat menambah baru
            setFormData({ kode_shift: '', jam_masuk: '08:00', jam_pulang: '17:00', hari_berikutnya: false });
        }
    }, [shift]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData({ ...formData, [e.target.id]: e.target.value });
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        const payload = { ...formData, jam_masuk: `${formData.jam_masuk}:00`, jam_pulang: `${formData.jam_pulang}:00`};
        try {
            if (shift) {
                await api.put(`/shift/${shift.shift_id}`, payload);
                alert("Shift berhasil diperbarui.");
            } else {
                await api.post('/shift', payload);
                alert("Shift baru berhasil ditambahkan.");
            }
            onSuccess();
        } catch (error: any) {
            const errorMessages = error.response?.data?.errors;
            let message = error.response?.data?.message || "Gagal menyimpan data.";
            if (errorMessages) {
                message += "\n" + Object.values(errorMessages).flat().join("\n");
            }
            alert(message);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div><Label htmlFor="kode_shift">Kode Shift</Label><Input id="kode_shift" value={formData.kode_shift} onChange={handleChange} required disabled={isLoading} /></div>
            <div><Label htmlFor="jam_masuk">Jam Masuk</Label><Input id="jam_masuk" type="time" value={formData.jam_masuk} onChange={handleChange} required disabled={isLoading} /></div>
            <div><Label htmlFor="jam_pulang">Jam Pulang</Label><Input id="jam_pulang" type="time" value={formData.jam_pulang} onChange={handleChange} required disabled={isLoading} /></div>
            <div className="flex items-center space-x-2">
                <Switch id="hari_berikutnya" checked={formData.hari_berikutnya} onCheckedChange={(checked) => setFormData({...formData, hari_berikutnya: checked})} disabled={isLoading} />
                <Label htmlFor="hari_berikutnya">Pulang di Hari Berikutnya?</Label>
            </div>
            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onCancel} disabled={isLoading}>Batal</Button>
                <Button type="submit" disabled={isLoading}>{isLoading ? "Menyimpan..." : "Simpan"}</Button>
            </DialogFooter>
        </form>
    );
};

// --- Komponen Utama Modul (Tidak ada perubahan di sini) ---
export function ShiftModule() {
    const [data, setData] = useState<Shift[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selected, setSelected] = useState<Shift | undefined>(undefined);

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        try {
            const response = await api.get<Shift[]>('/shift');
            setData(response.data);
        } catch (error) {
            console.error("Gagal mengambil data shift:", error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => { fetchData() }, [fetchData]);

    const handleSuccess = () => {
        fetchData();
        setDialogOpen(false);
    };

    const columns: ColumnDef<Shift>[] = [
        { accessorKey: "kode_shift", header: "Kode Shift" },
        { accessorKey: "jam_masuk", header: "Jam Masuk" },
        { accessorKey: "jam_pulang", header: "Jam Pulang" },
        { accessorKey: "hari_berikutnya", header: "Hari Berikutnya", cell: ({ row }) => row.original.hari_berikutnya ? "Ya" : "Tidak" },
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
                <CardTitle>Daftar Shift Kerja</CardTitle>
                <CardDescription>Kelola semua shift yang berlaku di perusahaan.</CardDescription>
            </CardHeader>
            <CardContent>
                 <div className="flex justify-end mb-4">
                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild><Button onClick={() => setSelected(undefined)}>Tambah Shift</Button></DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{selected ? "Edit Shift" : "Tambah Shift Baru"}</DialogTitle>
                            </DialogHeader>
                            <ShiftForm onSuccess={handleSuccess} shift={selected} onCancel={() => setDialogOpen(false)} />
                        </DialogContent>
                    </Dialog>
                </div>
                <DataTable columns={columns} data={data} isLoading={isLoading} />
            </CardContent>
        </Card>
    );
}