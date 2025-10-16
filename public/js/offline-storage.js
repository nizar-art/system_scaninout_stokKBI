/**
 * Sistem penyimpanan lokal dengan enkripsi untuk aplikasi STO KBI
 * Memungkinkan aplikasi bekerja dengan koneksi internet lambat atau terputus
 */

// Mini library untuk enkripsi sederhana
class SimpleEncryption {
    constructor(secretKey = 'STO-KBI-SECURE-KEY') {
        this.secretKey = secretKey;
    }

    // Enkripsi data dengan XOR + Base64
    encrypt(data) {
        if (!data) return null;

        try {
            // Konversi data ke JSON string
            const jsonString = typeof data === 'string' ? data : JSON.stringify(data);

            // Enkripsi dengan XOR cipher
            let encrypted = '';
            for (let i = 0; i < jsonString.length; i++) {
                const charCode = jsonString.charCodeAt(i) ^ this.secretKey.charCodeAt(i % this.secretKey.length);
                encrypted += String.fromCharCode(charCode);
            }

            // Konversi ke Base64 untuk penyimpanan yang aman
            return btoa(encrypted);
        } catch (e) {
            console.error('Encryption error:', e);
            return null;
        }
    }

    // Dekripsi data dari Base64 + XOR
    decrypt(encryptedData) {
        if (!encryptedData) return null;

        try {
            // Decode dari Base64
            const base64Decoded = atob(encryptedData);

            // Dekripsi dari XOR cipher
            let decrypted = '';
            for (let i = 0; i < base64Decoded.length; i++) {
                const charCode = base64Decoded.charCodeAt(i) ^ this.secretKey.charCodeAt(i % this.secretKey.length);
                decrypted += String.fromCharCode(charCode);
            }

            // Parse JSON
            return JSON.parse(decrypted);
        } catch (e) {
            console.error('Decryption error:', e);
            return null;
        }
    }
}

// Kelas untuk mengelola penyimpanan offline dengan enkripsi dan validasi token
class SecureOfflineStorage {
    constructor() {
        this.DB_NAME = 'sto_kbi_offline';
        this.DB_VERSION = 1;
        this.STORE_NAME = 'offline_reports';
        this.TOKEN_STORE = 'auth_tokens';
        this.db = null;
        this.encryption = new SimpleEncryption();
        this.tokenTimeout = 7 * 24 * 60 * 60 * 1000; // 7 hari dalam milidetik
        this.cleanupInterval = 3 * 24 * 60 * 60 * 1000; // 3 hari
        this.initDB();
    }

    // Inisialisasi database
    initDB() {
        return new Promise((resolve, reject) => {
            if (this.db) {
                resolve(this.db);
                return;
            }

            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);

            request.onerror = (event) => {
                console.error('IndexedDB error:', event.target.errorCode);
                reject('Tidak dapat membuka database offline');
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Buat object store untuk laporan offline
                if (!db.objectStoreNames.contains(this.STORE_NAME)) {
                    const store = db.createObjectStore(this.STORE_NAME, {
                        keyPath: 'id',
                        autoIncrement: true
                    });
                    store.createIndex('inventory_id', 'inventory_id', {
                        unique: false
                    });
                    store.createIndex('status', 'status', {
                        unique: false
                    });
                    store.createIndex('timestamp', 'timestamp', {
                        unique: false
                    });
                    store.createIndex('sync_date', 'sync_date', {
                        unique: false
                    });
                }

                // Buat object store untuk token
                if (!db.objectStoreNames.contains(this.TOKEN_STORE)) {
                    const tokenStore = db.createObjectStore(this.TOKEN_STORE, {
                        keyPath: 'id',
                        autoIncrement: true
                    });
                    tokenStore.createIndex('token', 'token', {
                        unique: true
                    });
                    tokenStore.createIndex('created_at', 'created_at', {
                        unique: false
                    });
                }
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;

                // Mulai auto cleanup untuk data yang sudah tersinkronisasi
                this.scheduleCleanup();

                resolve(this.db);
            };
        });
    }

    // Simpan token autentikasi (dipanggil saat login)
    saveAuthToken(token) {
        return new Promise((resolve, reject) => {
            this.initDB().then((db) => {
                const transaction = db.transaction([this.TOKEN_STORE], 'readwrite');
                const store = transaction.objectStore(this.TOKEN_STORE);

                // Enkripsi token sebelum disimpan
                const encryptedToken = this.encryption.encrypt(token);

                const tokenData = {
                    token: encryptedToken,
                    created_at: new Date().toISOString(),
                    expires_at: new Date(Date.now() + this.tokenTimeout).toISOString()
                };

                const request = store.add(tokenData);

                request.onsuccess = () => {
                    resolve(true);
                };

                request.onerror = () => {
                    reject('Gagal menyimpan token');
                };
            }).catch(reject);
        });
    }

    // Ambil token yang masih valid
    getValidToken() {
        return new Promise((resolve, reject) => {
            this.initDB().then((db) => {
                const transaction = db.transaction([this.TOKEN_STORE], 'readonly');
                const store = transaction.objectStore(this.TOKEN_STORE);
                const index = store.index('created_at');

                // Ambil token terbaru berdasarkan created_at
                const request = index.openCursor(null, 'prev');

                request.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        const tokenData = cursor.value;
                        const now = new Date();
                        const expiresAt = new Date(tokenData.expires_at);

                        if (now < expiresAt) {
                            // Token masih valid, dekripsi dan kembalikan
                            const decryptedToken = this.encryption.decrypt(tokenData.token);
                            resolve(decryptedToken);
                        } else {
                            // Token expired, hapus dan resolve null
                            const deleteRequest = store.delete(tokenData.id);
                            deleteRequest.onsuccess = () => {
                                resolve(null);
                            };
                        }
                    } else {
                        // Tidak ada token
                        resolve(null);
                    }
                };

                request.onerror = () => {
                    reject('Gagal mengambil token');
                };
            }).catch(reject);
        });
    }

    // Simpan data report dengan enkripsi
    saveReport(inventoryId, formData) {
        return new Promise((resolve, reject) => {
            this.initDB().then((db) => {
                const transaction = db.transaction([this.STORE_NAME], 'readwrite');
                const store = transaction.objectStore(this.STORE_NAME);

                const timestamp = new Date().toISOString();

                // Data yang akan dienkripsi
                const reportData = {
                    inventory_id: inventoryId,
                    form_data: formData,
                    timestamp: timestamp
                };

                // Enkripsi data
                const encryptedData = this.encryption.encrypt(reportData);

                // Data yang disimpan di IndexedDB
                const report = {
                    inventory_id: inventoryId, // Tetap simpan ini unencrypted untuk indeks
                    encrypted_data: encryptedData,
                    timestamp: timestamp, // Tetap simpan ini unencrypted untuk indeks
                    status: 'pending',
                    created_at: timestamp,
                    sync_attempts: 0,
                    last_sync_attempt: null,
                    sync_date: null
                };

                const request = store.add(report);

                request.onsuccess = () => {
                    resolve(report);
                };

                request.onerror = (event) => {
                    console.error('Error saving offline report:', event.target.error);
                    reject('Gagal menyimpan data offline');
                };
            }).catch(reject);
        });
    }

    // Ambil semua laporan yang belum tersinkronisasi
    getPendingReports() {
        return new Promise((resolve, reject) => {
            this.initDB().then((db) => {
                const transaction = db.transaction([this.STORE_NAME], 'readonly');
                const store = transaction.objectStore(this.STORE_NAME);
                const index = store.index('status');

                const request = index.getAll('pending');

                request.onsuccess = (event) => {
                    const encryptedReports = event.target.result;

                    // Dekripsi semua laporan
                    const reports = encryptedReports.map(report => {
                        try {
                            const decryptedData = this.encryption.decrypt(report.encrypted_data);
                            return {
                                id: report.id,
                                inventory_id: decryptedData.inventory_id,
                                form_data: decryptedData.form_data,
                                timestamp: decryptedData.timestamp
                            };
                        } catch (e) {
                            console.error('Decryption error for report:', report.id);
                            return null;
                        }
                    }).filter(report => report !== null); // Hapus item yang gagal didekripsi

                    resolve(reports);
                };

                request.onerror = (event) => {
                    console.error('Error getting pending reports:', event.target.error);
                    reject('Gagal mengambil data offline');
                };
            }).catch(reject);
        });
    }

    // Update status laporan setelah sinkronisasi
    updateReportStatus(id, newStatus, reportId = null) {
        return new Promise((resolve, reject) => {
            this.initDB().then((db) => {
                const transaction = db.transaction([this.STORE_NAME], 'readwrite');
                const store = transaction.objectStore(this.STORE_NAME);

                const getRequest = store.get(id);

                getRequest.onsuccess = (event) => {
                    const report = event.target.result;
                    if (report) {
                        // Update status dan tambahkan informasi sinkronisasi
                        report.status = newStatus;
                        report.last_sync_attempt = new Date().toISOString();
                        report.sync_attempts += 1;

                        if (newStatus === 'synced') {
                            report.sync_date = new Date().toISOString();
                        }

                        if (reportId) {
                            report.server_report_id = reportId;
                        }

                        const updateRequest = store.put(report);
                        updateRequest.onsuccess = () => resolve(report);
                        updateRequest.onerror = (event) => {
                            console.error('Error updating report status:', event.target.error);
                            reject('Gagal update status laporan');
                        };
                    } else {
                        reject('Laporan tidak ditemukan');
                    }
                };

                getRequest.onerror = (event) => {
                    console.error('Error getting report for status update:', event.target.error);
                    reject('Gagal mengambil laporan untuk update');
                };
            }).catch(reject);
        });
    }

    // Auto-cleanup untuk laporan yang sudah tersinkronisasi
    cleanupSyncedReports() {
        return new Promise((resolve, reject) => {
            this.initDB().then((db) => {
                const transaction = db.transaction([this.STORE_NAME], 'readwrite');
                const store = transaction.objectStore(this.STORE_NAME);
                const index = store.index('sync_date');

                // Tanggal batas untuk membersihkan data (3 hari yang lalu)
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - 3);
                const cutoffString = cutoffDate.toISOString();

                // Range untuk mencari semua record yang tersinkronisasi sebelum cutoff date
                const range = IDBKeyRange.upperBound(cutoffString);
                let deletedCount = 0;

                // Gunakan cursor untuk menghapus data
                const cursorRequest = index.openCursor(range);

                cursorRequest.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        const report = cursor.value;
                        if (report.status === 'synced') {
                            // Hapus laporan yang sudah tersinkronisasi lebih dari 3 hari
                            const deleteRequest = store.delete(cursor.primaryKey);
                            deleteRequest.onsuccess = () => {
                                deletedCount++;
                            };
                        }
                        cursor.continue();
                    } else {
                        console.log(`Cleaned up ${deletedCount} old synced reports`);
                        resolve(deletedCount);
                    }
                };

                cursorRequest.onerror = (event) => {
                    console.error('Error during cleanup:', event.target.error);
                    reject('Gagal membersihkan data lama');
                };
            }).catch(reject);
        });
    }

    // Jadwalkan pembersihan otomatis
    scheduleCleanup() {
        // Jalankan pembersihan pertama kali
        this.cleanupSyncedReports()
            .then(count => {
                console.log(`Initial cleanup: ${count} reports removed`);
            })
            .catch(error => {
                console.error('Cleanup error:', error);
            });

        // Jadwalkan pembersihan secara berkala (setiap 24 jam)
        setInterval(() => {
            this.cleanupSyncedReports()
                .then(count => {
                    console.log(`Scheduled cleanup: ${count} reports removed`);
                })
                .catch(error => {
                    console.error('Scheduled cleanup error:', error);
                });
        }, 24 * 60 * 60 * 1000); // 24 jam
    }

    // Sinkronisasi data ke server dengan token validasi
    syncWithServer(apiUrl) {
        return new Promise((resolve, reject) => {
            // Dapatkan token validasi terlebih dahulu
            this.getValidToken().then((token) => {
                if (!token) {
                    console.warn('No valid token found for synchronization');
                    // Tetap lanjutkan tanpa token (less secure)
                }

                this.getPendingReports().then((pendingReports) => {
                    if (pendingReports.length === 0) {
                        resolve({
                            success: true,
                            processed: 0,
                            message: 'Tidak ada data untuk disinkronkan'
                        });
                        return;
                    }

                    // Tampilkan informasi sinkronisasi
                    this.showSyncNotification(pendingReports.length);

                    // Kirim data ke server dengan token otorisasi
                    fetch(apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Authorization': token ? `Bearer ${token}` : ''
                            },
                            body: JSON.stringify({
                                reports: pendingReports
                            })
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`Server response: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Update status untuk setiap laporan
                            const updatePromises = data.results.map(result => {
                                const report = pendingReports.find(r => r.timestamp === result.timestamp);
                                if (!report) return Promise.resolve();

                                return this.updateReportStatus(
                                    report.id,
                                    result.status === 'success' ? 'synced' : 'failed',
                                    result.report_id
                                );
                            });

                            return Promise.all(updatePromises).then(() => {
                                this.hideSyncNotification();
                                resolve(data);
                            });
                        })
                        .catch(error => {
                            console.error('Error syncing with server:', error);
                            this.hideSyncNotification('Gagal sinkronisasi');
                            reject('Gagal sinkronisasi dengan server: ' + error.message);
                        });
                }).catch(reject);
            }).catch(error => {
                console.error('Token validation error:', error);
                reject('Validasi token gagal: ' + error);
            });
        });
    }

    // Tampilkan notifikasi sinkronisasi
    showSyncNotification(count) {
        if (document.getElementById('sync-notification')) {
            document.getElementById('sync-notification').remove();
        }

        const notification = document.createElement('div');
        notification.id = 'sync-notification';
        notification.style = 'position:fixed;top:20px;right:20px;background:#007bff;color:white;padding:15px;border-radius:5px;z-index:9999;';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm text-white mr-2" role="status"></div>
                <span>Menyinkronkan ${count} data offline...</span>
            </div>
        `;
        document.body.appendChild(notification);
    }

    // Sembunyikan notifikasi sinkronisasi
    hideSyncNotification(errorMessage = null) {
        const notification = document.getElementById('sync-notification');
        if (notification) {
            if (errorMessage) {
                notification.style.background = '#dc3545';
                notification.innerHTML = `<span>${errorMessage}</span>`;
                setTimeout(() => {
                    notification.remove();
                }, 5000);
            } else {
                notification.style.background = '#28a745';
                notification.innerHTML = `<span>Sinkronisasi berhasil!</span>`;
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
        }
    }

    // Periksa apakah ada data pending
    hasPendingData() {
        return new Promise((resolve) => {
            this.getPendingReports().then((reports) => {
                resolve(reports.length > 0);
            }).catch(() => {
                resolve(false);
            });
        });
    }

    // Hitung jumlah data pending
    countPendingData() {
        return new Promise((resolve) => {
            this.getPendingReports().then((reports) => {
                resolve(reports.length);
            }).catch(() => {
                resolve(0);
            });
        });
    }
}

// Inisialisasi storage
const offlineStorage = new SecureOfflineStorage();

// Fungsi untuk mengecek koneksi
function isOnline() {
    return navigator.onLine;
}

// Coba sinkronisasi saat online
window.addEventListener('online', () => {
    console.log('Koneksi online terdeteksi, mencoba sinkronisasi...');
    offlineStorage.hasPendingData().then(hasPending => {
        if (hasPending) {
            const syncUrl = document.querySelector('meta[name="offline-sync-url"]') ? document.querySelector('meta[name="offline-sync-url"]').getAttribute('content') ||  '/api/sync-offline-data' : '/api/sync-offline-data';
            offlineStorage.syncWithServer(syncUrl)
                .then(result => {
                    if (result.processed > 0) {
                        // Tampilkan notifikasi sukses
                        const message = `Berhasil menyinkronkan ${result.processed} data`;
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: message,
                                showConfirmButton: false,
                                timer: 3000
                            });
                        } else {
                            alert(message);
                        }
                    }
                })
                .catch(err => console.error('Sync error:', err));
        }
    });
});

// Tambahkan indikator offline di navbar jika ada data pending
document.addEventListener('DOMContentLoaded', function () {
    // Periksa setiap 30 detik apakah ada data pending
    function updateOfflineIndicator() {
        offlineStorage.countPendingData().then(count => {
            const navbarBrand = document.querySelector('.navbar-brand');
            const existingBadge = document.getElementById('offline-data-badge');

            if (count > 0) {
                if (!existingBadge) {
                    const badge = document.createElement('span');
                    badge.id = 'offline-data-badge';
                    badge.className = 'badge badge-warning ml-2';
                    badge.style = 'vertical-align: middle;';
                    badge.textContent = count;
                    badge.title = `${count} data menunggu untuk disinkronkan`;

                    if (navbarBrand) {
                        navbarBrand.appendChild(badge);
                    }
                } else {
                    existingBadge.textContent = count;
                }
            } else if (existingBadge) {
                existingBadge.remove();
            }
        });
    }

    // Update indikator saat halaman dimuat
    updateOfflineIndicator();

    // Periksa setiap 30 detik
    setInterval(updateOfflineIndicator, 30000);

    // Simpan token dari meta tag jika tersedia
    const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : null;
    if (csrfToken) {
        offlineStorage.saveAuthToken(csrfToken)
            .then(() => console.log('Auth token saved for offline sync'))
            .catch(err => console.warn('Failed to save auth token:', err));
    }
});