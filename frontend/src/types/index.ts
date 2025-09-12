// frontend/src/types/index.ts

export type Role = 'karyawan' | 'hr' | 'direktur' | 'it_dev';

export interface Departemen {
  departemen_id: number;
  nama_departemen: string;
  menggunakan_shift: boolean;
  karyawan_count?: number; 
}

export interface Jabatan {
  jabatan_id: number;
  nama_jabatan: string;
}

export interface Shift {
  shift_id: number;
  kode_shift: string;
  jam_masuk: string;
  jam_pulang: string;
  hari_berikutnya: boolean;
}

// Tambahkan semua field dan properti 'role'
export interface Karyawan {
  karyawan_id: number;
  nik: string;
  nama_lengkap: string;
  email: string;
  role: Role; // <-- TAMBAHKAN INI
  tanggal_masuk: string;
  status: 'Aktif' | 'Resign';
  departemen_id_saat_ini?: number;
  jabatan_id_saat_ini?: number;
  departemen_saat_ini?: Departemen;
  jabatan_saat_ini?: Jabatan;
  tempat_lahir?: string;
  tanggal_lahir?: string;
  jenis_kelamin?: 'Laki-laki' | 'Perempuan';
  alamat?: string;
  status_perkawinan?: 'Belum Menikah' | 'Menikah' | 'Cerai';
  nomor_telepon?: string;
  kategori_gaji?: 'Bulanan' | 'Harian' | 'Borongan';
  jam_kerja_masuk?: string;
  jam_kerja_pulang?: string;
}

