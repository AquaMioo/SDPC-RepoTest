import { useEffect, useState } from 'react';

export type AnnouncementImage = {
    id: number | string;
    url: string;
};

/** How long each picture stays before the next slides in. */
const SLIDE_MS = 5000;

/** How long one picture takes to slide across. */
const SLIDE_TRANSITION_MS = 600;

/** Whether the viewer asked their system for reduced motion. */
function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * The Announcement's pictures (owner, 2026-10-18), for both dashboards and the
 * admin's preview.
 *
 * Nothing in it can be clicked (owner, 2026-10-18): no link, no arrows. One
 * picture is drawn still. Two or more slide in from the right every few
 * seconds, with dots that only show which one is up; a copy of the first
 * trails the last so the strip keeps moving forward and snaps back unseen. It
 * holds still while pointed at, and for anybody who asked for reduced motion.
 * Each picture covers the whole announcement box.
 */
export function AnnouncementCarousel({
    images,
}: {
    images: AnnouncementImage[];
}) {
    if (images.length === 0) {
        return null;
    }

    if (images.length === 1) {
        return (
            <div
                data-test="announcement-image"
                style={{ width: '100%', height: '100%' }}
            >
                <Picture image={images[0]} index={0} />
            </div>
        );
    }

    return <Slider images={images} />;
}

function Slider({ images }: { images: AnnouncementImage[] }) {
    /* 0 to images.length; the last is the trailing copy of picture 1. */
    const [position, setPosition] = useState(0);
    const [animated, setAnimated] = useState(true);
    const [paused, setPaused] = useState(false);
    const count = images.length;
    const current = position % count;

    useEffect(() => {
        if (paused || prefersReducedMotion()) {
            return;
        }

        const timer = window.setInterval(() => {
            setAnimated(true);
            setPosition((index) => Math.min(index + 1, count));
        }, SLIDE_MS);

        return () => window.clearInterval(timer);
    }, [paused, count]);

    useEffect(() => {
        if (position !== count) {
            return;
        }

        const timer = window.setTimeout(() => {
            setAnimated(false);
            setPosition(0);
        }, SLIDE_TRANSITION_MS);

        return () => window.clearTimeout(timer);
    }, [position, count]);

    return (
        <div
            data-test="announcement-carousel"
            aria-roledescription="carousel"
            aria-label="Announcement"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            style={{
                position: 'relative',
                width: '100%',
                height: '100%',
                overflow: 'hidden',
                borderRadius: 'var(--radius-md)',
            }}
        >
            <div
                style={{
                    display: 'flex',
                    height: '100%',
                    transform: `translateX(-${position * 100}%)`,
                    transition: animated
                        ? `transform ${SLIDE_TRANSITION_MS}ms cubic-bezier(0.65, 0, 0.35, 1)`
                        : 'none',
                }}
            >
                {[...images, images[0]].map((image, index) => (
                    <div
                        key={`${image.id}-${index}`}
                        aria-hidden={index !== position}
                        style={{ flex: '0 0 100%', height: '100%' }}
                    >
                        <Picture image={image} index={index % count} />
                    </div>
                ))}
            </div>

            <div
                aria-hidden
                style={{
                    position: 'absolute',
                    bottom: 8,
                    left: '50%',
                    transform: 'translateX(-50%)',
                    display: 'flex',
                    gap: 6,
                    padding: '5px 8px',
                    borderRadius: 999,
                    background: 'rgba(0, 0, 0, 0.32)',
                }}
            >
                {images.map((image, index) => (
                    <span
                        key={image.id}
                        style={{
                            width: index === current ? 16 : 7,
                            height: 7,
                            borderRadius: 999,
                            background:
                                index === current
                                    ? '#ffffff'
                                    : 'rgba(255, 255, 255, 0.55)',
                            transition: 'width 0.3s ease',
                        }}
                    />
                ))}
            </div>
        </div>
    );
}

/** One picture covering the whole box (cropped to fit), not clickable. */
function Picture({
    image,
    index,
}: {
    image: AnnouncementImage;
    index: number;
}) {
    return (
        <div
            style={{
                width: '100%',
                height: '100%',
                overflow: 'hidden',
                borderRadius: 'var(--radius-md)',
                background:
                    'color-mix(in srgb, var(--color-text) 5%, transparent)',
            }}
        >
            <img
                src={image.url}
                alt={`Announcement picture ${index + 1}`}
                style={{
                    width: '100%',
                    height: '100%',
                    objectFit: 'cover',
                    display: 'block',
                }}
            />
        </div>
    );
}
