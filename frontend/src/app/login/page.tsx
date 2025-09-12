"use client";

import { useState } from 'react';
import { useAuth } from '@/context/AuthContext'; // <-- Logika diimpor
import { motion } from 'framer-motion';

export default function LoginPage() {
  const [nik, setNik] = useState('1111');
  const [password, setPassword] = useState('tunasesta1111');
  const { login } = useAuth(); // <-- Logika dipanggil

  // Fungsi ini sekarang akan memanggil fungsi login yang sebenarnya
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await login(nik, password); 
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-green-50 via-yellow-50 to-blue-50 dark:from-slate-900 dark:via-green-900/20 dark:to-blue-900/20">
      {/* Background Pattern */}
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-1/2 -left-1/2 w-full h-full bg-gradient-to-r from-green-400/10 to-yellow-400/10 rounded-full blur-3xl"></div>
        <div className="absolute -bottom-1/2 -right-1/2 w-full h-full bg-gradient-to-l from-blue-400/10 to-green-400/10 rounded-full blur-3xl"></div>
      </div>

      <div className="relative z-10 flex flex-col lg:flex-row min-h-screen">
        {/* Left Side - Company Branding (Hidden on mobile) */}
        <div className="hidden lg:flex lg:w-1/2 xl:w-2/3 flex-col justify-center items-center p-12 text-center">
          <motion.div
            initial={{ opacity: 0, x: -50 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.8, ease: "easeOut" }}
            className="max-w-lg"
          >
            {/* Company Logo Placeholder */}
            <div className="mb-8">
              <div className="relative mx-auto w-32 h-32 lg:w-40 lg:h-40 mb-6">
                <div className="absolute inset-0 bg-gradient-to-r from-green-600 to-yellow-500 rounded-2xl rotate-3 opacity-20"></div>
                <div className="relative bg-gradient-to-br from-green-500 via-yellow-400 to-blue-500 rounded-2xl p-8 text-white shadow-2xl">
                  <svg className="w-16 h-16 lg:w-20 lg:h-20 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 21h18l-9-18-9 18zM12 8v6M12 16v2"/>
                  </svg>
                </div>
              </div>
              <h1 className="text-4xl lg:text-6xl font-bold bg-gradient-to-r from-green-700 via-yellow-600 to-blue-600 bg-clip-text text-transparent mb-4">
                Tunas Esta
              </h1>
              <p className="text-lg lg:text-xl text-slate-600 dark:text-slate-300 leading-relaxed">
                Human Resource Information System
              </p>
            </div>
          </motion.div>
        </div>

        {/* Right Side - Login Form */}
        <div className="w-full lg:w-1/2 xl:w-1/3 flex items-center justify-center p-4 lg:p-8">
          <motion.div
            initial={{ opacity: 0, y: 30, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ duration: 0.6, ease: "easeOut" }}
            className="w-full max-w-md"
          >
            {/* Main Card */}
            <div className="bg-white/90 dark:bg-slate-800/90 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/30 dark:border-slate-700/50 p-6 lg:p-8">
              
              {/* Mobile Logo (Visible only on mobile) */}
              <motion.div 
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
                className="text-center mb-6 lg:hidden"
              >
                <div className="relative mx-auto w-16 h-16 mb-3">
                  <div className="absolute inset-0 bg-gradient-to-r from-green-600 to-yellow-500 rounded-xl rotate-6"></div>
                  <div className="relative bg-gradient-to-r from-green-500 to-yellow-400 rounded-xl p-3 text-white">
                    <svg className="w-10 h-10 mx-auto" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M3 21h18l-9-18-9 18zM12 8v6M12 16v2"/>
                    </svg>
                  </div>
                </div>
                <h1 className="text-2xl font-bold bg-gradient-to-r from-green-700 via-yellow-600 to-blue-600 bg-clip-text text-transparent">
                  Tunas Esta
                </h1>
              </motion.div>

              {/* Form Header */}
              <div className="text-center lg:text-left mb-6 lg:mb-8">
                <h2 className="text-2xl lg:text-3xl font-bold text-slate-800 dark:text-white mb-2">
                  Login
                </h2>
                <p className="text-slate-600 dark:text-slate-400 text-sm lg:text-base">
                  Halaman Login Tunas Esta
                </p>
              </div>

              {/* Login Form */}
              <motion.form
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ delay: 0.3 }}
                onSubmit={handleSubmit}
                className="space-y-4 lg:space-y-6"
              >
                {/* NIK Input */}
                <div>
                  <label htmlFor="nik" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    NIK (Employee ID)
                  </label>
                  <div className="relative">
                    <input
                      id="nik"
                      type="text"
                      placeholder="Masukkan NIK Anda"
                      value={nik}
                      onChange={(e) => setNik(e.target.value)}
                      required
                      className="w-full px-4 py-3 lg:py-4 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 text-sm lg:text-base"
                    />
                    <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                      <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                  </div>
                </div>

                {/* Password Input */}
                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    Password
                  </label>
                  <div className="relative">
                    <input
                      id="password"
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      className="w-full px-4 py-3 lg:py-4 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 text-sm lg:text-base"
                      placeholder="Masukkan password Anda"
                    />
                    <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                      <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </div>
                  </div>
                </div>

                {/* Remember & Forgot */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm">
                  <label className="flex items-center">
                    <input type="checkbox" className="rounded border-slate-300 text-green-600 focus:ring-green-500" />
                    <span className="ml-2 text-slate-600 dark:text-slate-400">Remember me</span>
                  </label>
                  <a href="#" className="text-green-600 hover:text-green-700 font-medium">
                    Forgot password?
                  </a>
                </div>

                {/* Login Button */}
                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  type="submit"
                  className="w-full py-3 lg:py-4 px-4 rounded-xl font-semibold text-white transition-all duration-200 bg-gradient-to-r from-green-600 via-yellow-500 to-blue-600 hover:from-green-700 hover:via-yellow-600 hover:to-blue-700 shadow-lg hover:shadow-xl text-sm lg:text-base"
                >
                  Login
                </motion.button>
              </motion.form>

              {/* Footer */}
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ delay: 0.5 }}
                className="mt-6 lg:mt-8 text-center"
              >
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  © 2025 Tunas Esta. All rights reserved.
                </p>
              </motion.div>
            </div>

            {/* Development Badge */}
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.7 }}
              className="mt-4 text-center"
            >
              <div className="inline-flex items-center px-3 py-1 bg-slate-200/80 dark:bg-slate-700/80 backdrop-blur-sm rounded-full text-xs text-slate-600 dark:text-slate-400">
                <div className="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                Development Mode
              </div>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </div>
  );
}
