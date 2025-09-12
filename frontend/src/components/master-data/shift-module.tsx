"use client";

import { useEffect, useState, useCallback } from "react";
import api from "@/lib/api";
import { ColumnDef } from "@tanstack/react-table";
import { MoreHorizontal } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { DataTable } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

// Define the Shift type
interface Shift {
    shift_id: number;
    nama_shift: string;
    jam_masuk: string;
    jam_pulang: string;
}

// Form Component
const ShiftForm = ({ onSuccess, shift, onCancel }: { onSuccess: () => void, shift?: Shift, onCancel: () => void }) => {
    const [namaShift, setNamaShift] = useState('');
    const [jamMasuk, setJamMasuk] = useState('');
    const [jamPulang, setJamPulang] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (shift) {
            setNamaShift(shift.nama_shift);
            setJamMasuk(shift.jam_masuk);
            setJamPulang(shift.jam_pulang);
        }
    }, [shift]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        const payload = { nama_shift: namaShift, jam_masuk: jamMasuk, jam_pulang: jamPulang };
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
            alert(error.response?.data?.message || "Gagal menyimpan data.");
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div>
                <Label htmlFor="nama_shift">Nama Shift</Label>
                <Input id="nama_shift" value={namaShift} onChange={(e) => setNamaShift(e.target.value)} required disabled={isLoading} />
            </div>
            <div>
                <Label htmlFor="jam_masuk">Jam Masuk</Label>
                <Input id="jam_masuk" type="time" value={jamMasuk} onChange={(e) => setJamMasuk(e.target.value)} required disabled={isLoading} />
            </div>
            <div>
                <Label htmlFor="jam_pulang">Jam Pulang</Label>
                <Input id="jam_pulang" type="time" value={jamPulang} onChange={(e) => setJamPulang(e.target.value)} required disabled={isLoading} />
            </div>
            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onCancel} disabled={isLoading}>Batal</Button>
                <Button type="submit" disabled={isLoading}>{isLoading ? "Menyimpan..." : "Simpan"}</Button>
            </DialogFooter>
        </form>
    );
};

// Main Module Component
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
        { accessorKey: "nama_shift", header: "Nama Shift" },
        { accessorKey: "jam_masuk", header: "Jam Masuk" },
        { accessorKey: "jam_pulang", header: "Jam Pulang" },
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
                <CardTitle>Daftar Shift</CardTitle>
                <CardDescription>Kelola semua jadwal shift yang ada di perusahaan.</CardDescription>
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