<?php

namespace Database\Seeders;

use App\Models\OfferingType;
use Illuminate\Database\Seeder;

/** F9.1 profiles from PRD §Jenis bisnis dan pustaka aset. Generic; no client is the reference. */
class OfferingTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'key' => 'event_ticket', 'label' => 'Tiket / event / workshop', 'primary_conversion' => 'ticket_booking',
                'conversion_events' => ['view_schedule', 'begin_checkout', 'ticket_booking'],
                'default_kpis' => ['ticket_booking', 'booking_conversion_rate', 'seat_fill_rate'],
                'content_pillars' => [['key' => 'process', 'name' => 'Proses & pengalaman'], ['key' => 'results', 'name' => 'Hasil & testimoni peserta'], ['key' => 'schedule', 'name' => 'Jadwal & booking']],
                'ctas' => ['Pesan tiket', 'Lihat jadwal'],
                'required_assets' => ['Foto & video sesi', 'Proses kegiatan', 'Hasil peserta', 'Tempat', 'Instruktur/pengisi', 'Testimoni'],
                'compliance_notes' => ['Sebut kebijakan refund saat menyebut harga tiket', 'Jadwal dan sisa kursi hanya dari data jadwal resmi'],
            ],
            [
                'key' => 'digital_app', 'label' => 'Aplikasi / produk digital', 'primary_conversion' => 'sign_up',
                'conversion_events' => ['app_store_click', 'install', 'sign_up', 'activation'],
                'default_kpis' => ['sign_up', 'install', 'activation_rate'],
                'content_pillars' => [['key' => 'feature', 'name' => 'Fitur & manfaat'], ['key' => 'story', 'name' => 'Cerita pengguna'], ['key' => 'education', 'name' => 'Edukasi topik']],
                'ctas' => ['Coba gratis', 'Unduh aplikasi'],
                'required_assets' => ['Screenshot & rekaman layar', 'Mockup perangkat', 'Ilustrasi/karakter brand', 'Cerita pengguna'],
                'compliance_notes' => ['Jangan menampilkan fitur yang belum ada', 'Klaim manfaat harus didukung data atau sumber'],
            ],
            [
                'key' => 'service', 'label' => 'Jasa / layanan', 'primary_conversion' => 'lead',
                'conversion_events' => ['contact_click', 'whatsapp_click', 'form_submit', 'lead'],
                'default_kpis' => ['lead', 'cost_per_lead', 'lead_to_customer_rate'],
                'content_pillars' => [['key' => 'expertise', 'name' => 'Keahlian & proses kerja'], ['key' => 'case_study', 'name' => 'Studi kasus'], ['key' => 'trust', 'name' => 'Testimoni & kepercayaan']],
                'ctas' => ['Konsultasi gratis', 'Hubungi kami'],
                'required_assets' => ['Foto tim & proses kerja', 'Studi kasus', 'Before-after', 'Testimoni'],
                'compliance_notes' => ['Before-after hanya dari klien nyata dengan izin', 'Hindari janji hasil yang dijamin'],
            ],
            [
                'key' => 'physical_product', 'label' => 'Produk fisik', 'primary_conversion' => 'purchase',
                'conversion_events' => ['view_item', 'add_to_cart', 'begin_checkout', 'purchase'],
                'default_kpis' => ['purchase', 'revenue', 'roas'],
                'content_pillars' => [['key' => 'product', 'name' => 'Produk & keunggulan'], ['key' => 'lifestyle', 'name' => 'Gaya hidup & pemakaian'], ['key' => 'review', 'name' => 'Ulasan pembeli']],
                'ctas' => ['Beli sekarang', 'Cek produk'],
                'required_assets' => ['Foto produk', 'Foto lifestyle', 'Ulasan pembeli'],
                'compliance_notes' => ['Harga dan stok hanya dari data resmi', 'Promo wajib menyebut periode berlaku'],
            ],
            [
                'key' => 'content_community', 'label' => 'Konten / komunitas', 'primary_conversion' => 'subscribe',
                'conversion_events' => ['subscribe', 'join', 'trial_start'],
                'default_kpis' => ['subscribe', 'member_growth', 'retention_rate'],
                'content_pillars' => [['key' => 'preview', 'name' => 'Cuplikan materi'], ['key' => 'creator', 'name' => 'Kreator & pengajar'], ['key' => 'community', 'name' => 'Highlight komunitas']],
                'ctas' => ['Gabung sekarang', 'Langganan'],
                'required_assets' => ['Cuplikan materi', 'Wajah kreator', 'Kutipan', 'Highlight komunitas'],
                'compliance_notes' => ['Kutipan anggota hanya dengan izin'],
            ],
        ];

        foreach ($types as $type) {
            OfferingType::query()->updateOrCreate(['key' => $type['key']], $type);
        }
    }
}
