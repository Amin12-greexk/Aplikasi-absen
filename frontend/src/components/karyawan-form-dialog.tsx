"use client"

import { useState, useEffect, ReactElement } from "react"
import { format } from "date-fns"
import { CalendarIcon } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { Calendar } from "@/components/ui/calendar"
import { Karyawan, Departemen, Jabatan } from "@/types"
import api from "@/lib/api"
import { cn } from "@/lib/utils"

interface KaryawanFormDialogProps {
    karyawan?: Karyawan;
    onSuccess: () => void;
    trigger?: ReactElement;
}

export function KaryawanFormDialog({ karyawan, onSuccess, trigger }: KaryawanFormDialogProps) {
    const [open, setOpen] = useState(false);
    const [departemenList, setDepartemenList] = useState<Departemen[]>([]);
    const [jabatanList, setJabatanList] = useState<Jabatan[]>([]);
    
    // Menggunakan useState untuk setiap field formulir
    const [formData, setFormData] = useState({
        nik: '',
        nama_lengkap: '',
        email: '',
        tanggal_masuk: new Date(),
        departemen_id_saat_ini: '',
        jabatan_id_saat_ini: '',
        jenis_kelamin: 'Laki-laki',
        status_perkawinan: 'Belum Menikah',
        kategori_gaji: 'Bulanan',
        status: 'Aktif',
        alamat: '',
        nomor_telepon: '',
        tempat_lahir: '',
        tanggal_lahir: undefined as Date | undefined,
    });

    useEffect(() => {
        // Mengisi form saat mode edit
        if (karyawan && open) {
            setFormData({
                nik: karyawan.nik || "",
                nama_lengkap: karyawan.nama_lengkap || "",
                email: karyawan.email || "",
                tanggal_masuk: karyawan.tanggal_masuk ? new Date(karyawan.tanggal_masuk) : new Date(),
                departemen_id_saat_ini: String(karyawan.departemen_id_saat_ini) || '',
                jabatan_id_saat_ini: String(karyawan.jabatan_id_saat_ini) || '',
                jenis_kelamin: karyawan.jenis_kelamin || "Laki-laki",
                status_perkawinan: karyawan.status_perkawinan || "Belum Menikah",
                kategori_gaji: karyawan.kategori_gaji || "Bulanan",
                status: karyawan.status || "Aktif",
                alamat: karyawan.alamat || "",
                nomor_telepon: karyawan.nomor_telepon || "",
                tempat_lahir: karyawan.tempat_lahir || "",
                tanggal_lahir: karyawan.tanggal_lahir ? new Date(karyawan.tanggal_lahir) : undefined,
            });
        } else {
            // Reset form untuk mode tambah
             setFormData({
                nik: '', nama_lengkap: '', email: '', tanggal_masuk: new Date(),
                departemen_id_saat_ini: '', jabatan_id_saat_ini: '',
                jenis_kelamin: 'Laki-laki', status_perkawinan: 'Belum Menikah',
                kategori_gaji: 'Bulanan', status: 'Aktif',
                alamat: '', nomor_telepon: '', tempat_lahir: '', tanggal_lahir: undefined
            });
        }
    }, [karyawan, open]);

    useEffect(() => {
        const fetchDependencies = async () => {
            try {
                const [deptRes, jabRes] = await Promise.all([
                    api.get('/departemen'), api.get('/jabatan')
                ]);
                setDepartemenList(deptRes.data);
                setJabatanList(jabRes.data);
            } catch (error) {
                console.error("Gagal mengambil data departemen/jabatan:", error);
            }
        };
        fetchDependencies();
    }, []);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const { id, value } = e.target;
        setFormData(prev => ({ ...prev, [id]: value }));
    };

    const handleSelectChange = (name: string, value: string) => {
        setFormData(prev => ({ ...prev, [name]: value }));
    };

    const handleDateChange = (name: string, value: Date | undefined) => {
         setFormData(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...formData,
            tanggal_masuk: format(formData.tanggal_masuk, 'yyyy-MM-dd'),
            tanggal_lahir: formData.tanggal_lahir ? format(formData.tanggal_lahir, 'yyyy-MM-dd') : null,
        };

        try {
            if (karyawan) {
                await api.put(`/karyawan/${karyawan.karyawan_id}`, payload);
                alert("Data karyawan berhasil diperbarui.");
            } else {
                await api.post('/karyawan', payload);
                alert("Karyawan baru berhasil ditambahkan.");
            }
            onSuccess();
            setOpen(false);
        } catch (error: any) {
             console.error("Gagal menyimpan data karyawan:", error);
             const errorMsg = error.response?.data?.message || "Gagal menyimpan data.";
             alert(`Error: ${errorMsg}`);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger || <Button>Tambah Karyawan</Button>}
            </DialogTrigger>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{karyawan ? "Edit Karyawan" : "Tambah Karyawan Baru"}</DialogTitle>
                </DialogHeader>
                 <form onSubmit={handleSubmit} className="space-y-4 max-h-[70vh] overflow-y-auto pr-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Kolom Kiri */}
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="nik">NIK</Label>
                            <Input id="nik" value={formData.nik} onChange={handleChange} required />
                        </div>
                        <div>
                            <Label htmlFor="nama_lengkap">Nama Lengkap</Label>
                            <Input id="nama_lengkap" value={formData.nama_lengkap} onChange={handleChange} required />
                        </div>
                        <div>
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" value={formData.email} onChange={handleChange} required />
                        </div>
                        <div>
                             <Label>Tanggal Masuk</Label>
                             <Popover>
                                <PopoverTrigger asChild>
                                    <Button variant={"outline"} className={cn("w-full justify-start text-left font-normal", !formData.tanggal_masuk && "text-muted-foreground")}>
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        {formData.tanggal_masuk ? format(formData.tanggal_masuk, "PPP") : <span>Pilih tanggal</span>}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0"><Calendar mode="single" selected={formData.tanggal_masuk} onSelect={(date) => handleDateChange('tanggal_masuk', date)} initialFocus /></PopoverContent>
                            </Popover>
                        </div>
                         <div>
                            <Label htmlFor="departemen_id_saat_ini">Departemen</Label>
                             <Select onValueChange={(value) => handleSelectChange('departemen_id_saat_ini', value)} value={String(formData.departemen_id_saat_ini)}>
                                <SelectTrigger><SelectValue placeholder="Pilih departemen..." /></SelectTrigger>
                                <SelectContent>{departemenList.map(d => <SelectItem key={d.departemen_id} value={String(d.departemen_id)}>{d.nama_departemen}</SelectItem>)}</SelectContent>
                            </Select>
                        </div>
                         <div>
                            <Label htmlFor="jabatan_id_saat_ini">Jabatan</Label>
                             <Select onValueChange={(value) => handleSelectChange('jabatan_id_saat_ini', value)} value={String(formData.jabatan_id_saat_ini)}>
                                <SelectTrigger><SelectValue placeholder="Pilih jabatan..." /></SelectTrigger>
                                <SelectContent>{jabatanList.map(j => <SelectItem key={j.jabatan_id} value={String(j.jabatan_id)}>{j.nama_jabatan}</SelectItem>)}</SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Kolom Kanan */}
                     <div className="space-y-4">
                        <div>
                            <Label htmlFor="tempat_lahir">Tempat Lahir</Label>
                            <Input id="tempat_lahir" value={formData.tempat_lahir} onChange={handleChange} />
                        </div>
                         <div>
                             <Label>Tanggal Lahir</Label>
                             <Popover>
                                <PopoverTrigger asChild>
                                    <Button variant={"outline"} className={cn("w-full justify-start text-left font-normal", !formData.tanggal_lahir && "text-muted-foreground")}>
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        {formData.tanggal_lahir ? format(formData.tanggal_lahir, "PPP") : <span>Pilih tanggal</span>}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-auto p-0"><Calendar mode="single" selected={formData.tanggal_lahir} onSelect={(date) => handleDateChange('tanggal_lahir', date)} /></PopoverContent>
                            </Popover>
                        </div>
                         <div>
                            <Label htmlFor="jenis_kelamin">Jenis Kelamin</Label>
                             <Select onValueChange={(value) => handleSelectChange('jenis_kelamin', value)} value={formData.jenis_kelamin}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Laki-laki">Laki-laki</SelectItem>
                                    <SelectItem value="Perempuan">Perempuan</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="status_perkawinan">Status Perkawinan</Label>
                             <Select onValueChange={(value) => handleSelectChange('status_perkawinan', value)} value={formData.status_perkawinan}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Belum Menikah">Belum Menikah</SelectItem>
                                    <SelectItem value="Menikah">Menikah</SelectItem>
                                    <SelectItem value="Cerai">Cerai</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                         <div>
                            <Label htmlFor="nomor_telepon">Nomor Telepon</Label>
                            <Input id="nomor_telepon" value={formData.nomor_telepon} onChange={handleChange} />
                        </div>
                         <div>
                            <Label htmlFor="alamat">Alamat</Label>
                            <Input id="alamat" value={formData.alamat} onChange={handleChange} />
                        </div>
                     </div>
                     <div className="md:col-span-2">
                        <Button type="submit" className="w-full">Simpan</Button>
                     </div>
                </form>
            </DialogContent>
        </Dialog>
    )
}

