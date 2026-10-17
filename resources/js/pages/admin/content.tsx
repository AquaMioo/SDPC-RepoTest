import { Head } from '@inertiajs/react';

import ContentEditor from '@/components/admin/content-editor';
import type { PostedAnnouncementImage } from '@/components/admin/content-editor';
import type { SiteContentDraft } from '@/types/admin';

type Props = {
    content?: Partial<Record<keyof SiteContentDraft, string | null>>;
    /** The Announcement's pictures, in order (owner, 2026-10-18). */
    announcementImages?: PostedAnnouncementImage[];
    announcementImageLimit?: number;
};

/**
 * Content Management — its own tab again (owner, 2026-10-07). The editor and
 * its save endpoint (AdminContentController::update) are unchanged; only the
 * screen moved off the dashboard overview. The Announcement is posted as
 * pictures since 2026-10-18.
 */
export default function AdminContent({
    content,
    announcementImages = [],
    announcementImageLimit = 10,
}: Props) {
    return (
        <div
            style={{
                maxWidth: 'clamp(1180px, 100vw - 320px, 1600px)',
                margin: '0 auto',
                padding: '30px clamp(16px, 4vw, 32px) 72px',
            }}
        >
            <Head title="Content management" />

            <ContentEditor
                content={content}
                announcementImages={announcementImages}
                announcementImageLimit={announcementImageLimit}
            />
        </div>
    );
}
