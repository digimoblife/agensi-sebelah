<?php

namespace App\Enums;

/** docs/WORKFLOWS.md §Content item. Values after `approved` are used from week 4 (render/publish). */
enum ContentStatus: string
{
    case Planned = 'planned';
    case Drafting = 'drafting';
    case Drafted = 'drafted';
    case GuardianFailed = 'guardian_failed';
    case AwaitingApproval = 'awaiting_approval';
    case RevisionRequested = 'revision_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Rendered = 'rendered';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case PublishFailed = 'publish_failed';
    case Packaged = 'packaged';
    case ManuallyUploaded = 'manually_uploaded';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::Drafting => 'Sedang ditulis',
            self::Drafted => 'Draf',
            self::GuardianFailed => 'Gagal cek Guardian',
            self::AwaitingApproval => 'Menunggu persetujuan',
            self::RevisionRequested => 'Revisi diminta',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Rendered => 'Visual siap',
            self::Scheduled => 'Terjadwal',
            self::Published => 'Terbit',
            self::PublishFailed => 'Gagal terbit',
            self::Packaged => 'Paket siap upload',
            self::ManuallyUploaded => 'Sudah diupload',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved, self::Rendered, self::Scheduled, self::Published, self::ManuallyUploaded => 'success',
            self::AwaitingApproval, self::Packaged => 'info',
            self::GuardianFailed, self::RevisionRequested, self::PublishFailed => 'warning',
            self::Rejected => 'danger',
            default => 'gray',
        };
    }
}
