<?php

/*
| Telegram bot messages (CLAUDE.md rule 11: fixed templates, never written by the AI). Telegram HTML parse mode:
| escape every interpolated value with e(). Tone: friendly, short, polite Indonesian.
*/

return [
    'linked_contact' => 'Halo :name! Akun Telegram ini sudah terhubung dengan <b>:client</b>. Konten yang perlu disetujui akan dikirim ke sini.',
    'linked_admin' => 'Terhubung sebagai admin Agensi Sebelah. Notifikasi dan pengingat akan dikirim ke sini.',
    'invite_invalid' => 'Kode undangan tidak berlaku atau sudah kedaluwarsa. Minta undangan baru ke tim kami ya.',
    'start_without_code' => 'Halo! Bot ini dipakai untuk persetujuan konten. Gunakan tautan undangan dari tim kami untuk menghubungkan akun.',
    'unknown_chat' => 'Akun ini belum terhubung. Gunakan tautan undangan dari tim kami.',

    'approval_header' => "<b>Konten untuk disetujui · :client</b>\n:date · :platform :format",
    'approval_caption' => "\n\n:caption",
    'approval_hashtags' => "\n\n:hashtags",
    'approval_video_note' => "\n\n<i>Format video: gambar di atas adalah sampul. Klip videonya menyusul.</i>",
    'btn_approve' => '✅ Setujui',
    'btn_revise' => '✏️ Minta revisi',
    'btn_reject' => '❌ Tolak',

    'approved' => 'Terima kasih, konten disetujui ✅',
    'ask_revision' => 'Silakan tulis apa yang perlu diubah (balas pesan ini dengan satu pesan).',
    'revision_received' => 'Catatan revisi diterima. Versi baru akan dikirim setelah selesai ✏️',
    'rejected' => 'Konten ditolak. Tim kami akan menyiapkan alternatif.',
    'expired' => 'Permintaan persetujuan ini sudah kedaluwarsa atau sudah diputuskan.',
    'not_allowed' => 'Akun ini tidak punya akses untuk menyetujui konten tersebut.',

    'admin_decision' => ':client · :contact :decision konten :date (:angle)',
    'admin_decision_approved' => 'menyetujui',
    'admin_decision_revise' => 'meminta revisi',
    'admin_decision_rejected' => 'menolak',
    'reminder_due' => "⏰ <b>Waktunya upload · :client</b>\n:platform :format · :time\nCaption ada di pesan berikut. Tekan tombol setelah diupload.",
    'reminder_missed' => "⚠️ <b>Jadwal terlewat · :client</b>\n:platform :format · seharusnya :time\nMac sedang mati/sleep, jadi tidak diposting otomatis. Silakan upload manual bila masih relevan.",
    'btn_uploaded' => '✅ Sudah diupload',
    'uploaded' => 'Dicatat sebagai sudah diupload. Terima kasih!',
    'publish_failed' => '❗ Gagal terbit · <b>:client</b> · :angle (kode :code). Buka dashboard untuk coba lagi atau upload manual.',
    'report_ready' => "📊 <b>Laporan mingguan :client</b>\n:period\n\n:summary",
    'admin_budget' => 'Budget AI <b>:client</b> bulan ini sudah :percent% ($:spent dari $:limit).',
];
