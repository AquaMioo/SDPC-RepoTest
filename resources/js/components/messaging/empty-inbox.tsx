import { Link, usePage } from '@inertiajs/react';
import {
    ChatCircleDotsIcon,
    MagnifyingGlassIcon,
    PaperPlaneRightIcon,
    SmileyIcon,
    UserPlusIcon,
} from '@phosphor-icons/react';

import { Btn } from '@/components/sdpc/btn';
import { Input } from '@/components/sdpc/input';
import { Panel } from '@/components/sdpc/panel';
import { useCurrentTeam } from '@/hooks/use-current-team';
import { index as recruitIndex } from '@/routes/recruit';
import { index as studentBoard } from '@/routes/student/board';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/**
 * Messages with nothing in it yet.
 *
 * The same two panes as a full inbox — the conversation list and the chat —
 * rather than a lone notice, so the screen already looks like what it becomes
 * (QA 2026-09-19). The chat pane says where a conversation comes from and
 * links there. A conversation always belongs to a project: a client starts
 * one from Recruit (Message on a student), a student by applying to a project
 * and messaging from the application.
 */
export default function EmptyInbox() {
    const team = useCurrentTeam();
    const role = usePage<{ auth?: { role?: string | null } }>().props.auth
        ?.role;
    const isStudent = role === 'student';

    return (
        <div
            className="msg-grid msg-grid-two"
            style={{ gap: 16, flex: 1, minHeight: 0, alignItems: 'stretch' }}
        >
            <Panel padding="none" gap="none" style={{ minHeight: 0 }}>
                <div
                    style={{
                        padding: '13px 14px 11px',
                        borderBottom: '1px solid var(--color-divider)',
                    }}
                >
                    <span
                        style={{
                            display: 'block',
                            fontSize: 10,
                            letterSpacing: '.12em',
                            textTransform: 'uppercase',
                            color: MUTED(50),
                            marginBottom: 8,
                        }}
                    >
                        Conversations
                    </span>

                    <Input
                        type="search"
                        placeholder="Find a chat…"
                        aria-label="Find a chat"
                        disabled
                        style={{ fontSize: 13 }}
                    />
                </div>

                <div
                    style={{
                        padding: '18px 16px',
                        fontSize: 12.5,
                        color: MUTED(60),
                    }}
                >
                    No conversations yet.
                </div>
            </Panel>

            <Panel
                padding="none"
                gap="none"
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    minHeight: 0,
                }}
            >
                <div
                    style={{
                        flex: 1,
                        display: 'grid',
                        placeItems: 'center',
                        padding: 32,
                        textAlign: 'center',
                    }}
                >
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            gap: 10,
                            maxWidth: 400,
                        }}
                    >
                        <span
                            aria-hidden="true"
                            style={{
                                width: 56,
                                height: 56,
                                borderRadius: '50%',
                                display: 'grid',
                                placeItems: 'center',
                                fontSize: 26,
                                color: 'var(--color-accent)',
                                background:
                                    'color-mix(in srgb, var(--color-accent) 12%, transparent)',
                            }}
                        >
                            <ChatCircleDotsIcon />
                        </span>

                        <div style={{ fontSize: 15 }}>No conversations yet</div>

                        <div
                            style={{
                                fontSize: 13,
                                lineHeight: 1.55,
                                color: MUTED(65),
                            }}
                        >
                            {isStudent
                                ? 'Apply to a project on Find a client. Once you’ve applied, you can message the business from your application.'
                                : 'Find a student on Recruit and press Message to start a conversation about one of your projects.'}
                        </div>

                        <Btn asChild variant="primary" style={{ marginTop: 6 }}>
                            <Link
                                href={
                                    isStudent
                                        ? studentBoard.url(team.slug)
                                        : recruitIndex.url(team.slug)
                                }
                            >
                                {isStudent ? (
                                    <>
                                        <MagnifyingGlassIcon />
                                        Find a client
                                    </>
                                ) : (
                                    <>
                                        <UserPlusIcon />
                                        Go to Recruit
                                    </>
                                )}
                            </Link>
                        </Btn>
                    </div>
                </div>

                {/* The composer, drawn but off until there is a chat to write in. */}
                <div
                    style={{
                        borderTop: '1px solid var(--color-divider)',
                        padding: '12px 14px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                    }}
                >
                    <SmileyIcon
                        aria-hidden="true"
                        style={{ fontSize: 20, color: MUTED(35), flex: 'none' }}
                    />
                    <Input
                        placeholder="Pick a conversation to start typing"
                        aria-label="Message"
                        disabled
                        style={{ flex: 1, fontSize: 13 }}
                    />
                    <Btn variant="primary" disabled aria-label="Send">
                        <PaperPlaneRightIcon />
                    </Btn>
                </div>
            </Panel>
        </div>
    );
}
