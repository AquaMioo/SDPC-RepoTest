import { PencilSimpleIcon } from '@phosphor-icons/react';
import { useRef, useState } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';

import type { Phase, TaskStatus } from '@/components/project-management/types';
import { Btn } from '@/components/sdpc/btn';
import { Panel } from '@/components/sdpc/panel';
import {
    dayNumber,
    isoFromDayNumber,
    monthLabel,
    monthStarts,
    shortDate,
    todayDayNumber,
} from '@/lib/calendar-days';

const MUTED = (pct: number) =>
    `color-mix(in srgb, var(--color-text) ${pct}%, transparent)`;

/** Days of air either side of the earliest start and the latest end. */
const PADDING_DAYS = 4;

type DragMode = 'move' | 'start' | 'end';

type Drag = {
    phaseId: number;
    mode: DragMode;
    originX: number;
    trackWidth: number;
    deltaDays: number;
};

/**
 * The Gantt chart on Project Management.
 *
 * One bar per phase across a shared date axis, with a dot per task (filled
 * once verified, ringed while waiting for review) and a line for today.
 *
 * The student drags a bar to move a phase, or drags either end to change how
 * long it runs; the pencil opens exact dates for anyone who would rather type
 * them, or who cannot drag on their device. The client sees the same chart and
 * cannot move anything — and the server refuses it either way.
 *
 * It draws the student's planned dates. Where those differ from what was
 * signed, the agreed dates are named beside the bar so the plan is always read
 * against the contract.
 */
export function PhaseTimeline({
    phases,
    canEdit,
    onReschedule,
    onEditDates,
}: {
    phases: Phase[];
    canEdit: boolean;
    onReschedule: (phase: Phase, startsOn: string, endsOn: string) => void;
    onEditDates: (phase: Phase) => void;
}) {
    const [drag, setDrag] = useState<Drag | null>(null);
    const trackRef = useRef<HTMLDivElement | null>(null);

    const dated = phases.filter(
        (phase) => phase.startsOn !== null && phase.endsOn !== null,
    );

    if (dated.length === 0) {
        return (
            <Panel padding="lg" gap="md">
                <TimelineTitle canEdit={false} />
                <span style={{ fontSize: 12.5, color: MUTED(62) }}>
                    No phase has dates yet.
                    {canEdit
                        ? ' Use the pencil on a phase to plan when it runs.'
                        : ' The student plans them here once they start.'}
                </span>
                {canEdit && (
                    <div style={{ display: 'grid', gap: 8 }}>
                        {phases.map((phase) => (
                            <div
                                key={phase.id}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                }}
                            >
                                <span style={{ fontSize: 13, flex: 1 }}>
                                    {phase.title}
                                </span>
                                <Btn
                                    icon
                                    variant="ghost"
                                    aria-label={`Set dates for ${phase.title}`}
                                    onClick={() => onEditDates(phase)}
                                >
                                    <PencilSimpleIcon />
                                </Btn>
                            </div>
                        ))}
                    </div>
                )}
            </Panel>
        );
    }

    /** A phase's start and end, with any drag in progress applied. */
    const span = (phase: Phase): [number, number] => {
        let start = dayNumber(phase.startsOn as string);
        let end = dayNumber(phase.endsOn as string);

        if (drag !== null && drag.phaseId === phase.id) {
            if (drag.mode === 'move') {
                start += drag.deltaDays;
                end += drag.deltaDays;
            } else if (drag.mode === 'start') {
                start = Math.min(start + drag.deltaDays, end);
            } else {
                end = Math.max(end + drag.deltaDays, start);
            }
        }

        return [start, end];
    };

    /*
     * The axis is fixed while a drag is under way. Recomputing it from the
     * dragged bar would slide every other bar under the pointer and make the
     * drag chase its own tail.
     */
    const baseStarts = dated.map((phase) =>
        dayNumber(phase.startsOn as string),
    );
    const baseEnds = dated.map((phase) => dayNumber(phase.endsOn as string));
    const from = Math.min(...baseStarts) - PADDING_DAYS;
    const to = Math.max(...baseEnds) + PADDING_DAYS;
    const total = Math.max(to - from, 1);

    const pct = (day: number) =>
        `${Math.min(Math.max(((day - from) / total) * 100, 0), 100)}%`;

    const today = todayDayNumber();

    const beginDrag = (
        event: ReactPointerEvent<HTMLElement>,
        phase: Phase,
        mode: DragMode,
    ) => {
        if (!canEdit || trackRef.current === null) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.setPointerCapture(event.pointerId);

        setDrag({
            phaseId: phase.id,
            mode,
            originX: event.clientX,
            trackWidth: trackRef.current.getBoundingClientRect().width,
            deltaDays: 0,
        });
    };

    const moveDrag = (event: ReactPointerEvent<HTMLElement>) => {
        if (drag === null) {
            return;
        }

        const deltaDays = Math.round(
            ((event.clientX - drag.originX) / drag.trackWidth) * total,
        );

        if (deltaDays !== drag.deltaDays) {
            setDrag({ ...drag, deltaDays });
        }
    };

    const endDrag = (phase: Phase) => {
        if (drag === null || drag.phaseId !== phase.id) {
            return;
        }

        const [start, end] = span(phase);

        setDrag(null);

        if (drag.deltaDays !== 0) {
            onReschedule(phase, isoFromDayNumber(start), isoFromDayNumber(end));
        }
    };

    return (
        <Panel padding="lg" gap="md">
            <TimelineTitle canEdit={canEdit} />

            {/* Too wide to shrink onto a phone: it scrolls in its own box. */}
            <div className="scroll-x">
                <div style={{ minWidth: 560 }}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '84px minmax(0, 1fr) 150px',
                            columnGap: 12,
                            rowGap: 14,
                            alignItems: 'center',
                        }}
                    >
                        {/* Month axis. */}
                        <span />
                        <div
                            ref={trackRef}
                            style={{ position: 'relative', height: 16 }}
                        >
                            {monthStarts(from, to).map((day) => (
                                <span
                                    key={day}
                                    style={{
                                        position: 'absolute',
                                        left: pct(day),
                                        fontSize: 10.5,
                                        letterSpacing: '0.06em',
                                        color: MUTED(50),
                                        transform: 'translateX(-50%)',
                                    }}
                                >
                                    {monthLabel(day)}
                                </span>
                            ))}
                        </div>
                        <span />

                        {phases.map((phase) => {
                            if (
                                phase.startsOn === null ||
                                phase.endsOn === null
                            ) {
                                return (
                                    <Row
                                        key={phase.id}
                                        phase={phase}
                                        canEdit={canEdit}
                                        onEditDates={onEditDates}
                                        dates="No dates yet"
                                    >
                                        <div
                                            style={{
                                                height: 22,
                                                borderRadius: 11,
                                                background: MUTED(6),
                                            }}
                                        />
                                    </Row>
                                );
                            }

                            const [start, end] = span(phase);
                            const isDragging =
                                drag !== null && drag.phaseId === phase.id;

                            return (
                                <Row
                                    key={phase.id}
                                    phase={phase}
                                    canEdit={canEdit}
                                    onEditDates={onEditDates}
                                    dates={`${shortDate(isoFromDayNumber(start))} – ${shortDate(isoFromDayNumber(end))}`}
                                    agreed={
                                        phase.isRescheduled
                                            ? `Agreed ${shortDate(phase.agreedStartsOn)} – ${shortDate(phase.agreedEndsOn)}`
                                            : null
                                    }
                                >
                                    <div
                                        style={{
                                            position: 'relative',
                                            height: 22,
                                            borderRadius: 11,
                                            background: MUTED(6),
                                        }}
                                    >
                                        {today >= from && today <= to && (
                                            <span
                                                aria-hidden="true"
                                                style={{
                                                    position: 'absolute',
                                                    left: pct(today),
                                                    top: -8,
                                                    bottom: -8,
                                                    borderLeft: `1px dashed ${MUTED(45)}`,
                                                    zIndex: 1,
                                                }}
                                            />
                                        )}

                                        <div
                                            role={canEdit ? 'slider' : 'img'}
                                            aria-label={`${phase.title}: ${shortDate(isoFromDayNumber(start))} to ${shortDate(isoFromDayNumber(end))}, ${phase.progress}% verified`}
                                            onPointerDown={(event) =>
                                                beginDrag(event, phase, 'move')
                                            }
                                            onPointerMove={moveDrag}
                                            onPointerUp={() => endDrag(phase)}
                                            onPointerCancel={() =>
                                                setDrag(null)
                                            }
                                            style={{
                                                position: 'absolute',
                                                left: pct(start),
                                                width: `max(28px, calc(${pct(end + 1)} - ${pct(start)}))`,
                                                top: 0,
                                                bottom: 0,
                                                borderRadius: 11,
                                                background:
                                                    phase.state === 'done'
                                                        ? 'color-mix(in srgb, var(--color-accent) 38%, transparent)'
                                                        : MUTED(16),
                                                boxShadow: isDragging
                                                    ? '0 0 0 2px var(--color-accent)'
                                                    : undefined,
                                                cursor: canEdit
                                                    ? isDragging
                                                        ? 'grabbing'
                                                        : 'grab'
                                                    : 'default',
                                                touchAction: canEdit
                                                    ? 'none'
                                                    : undefined,
                                                zIndex: 2,
                                                overflow: 'hidden',
                                            }}
                                        >
                                            <span
                                                style={{
                                                    position: 'absolute',
                                                    left: 8,
                                                    top: '50%',
                                                    transform:
                                                        'translateY(-50%)',
                                                    fontSize: 10,
                                                    color: MUTED(80),
                                                    pointerEvents: 'none',
                                                }}
                                            >
                                                {phase.progress}%
                                            </span>

                                            {phase.tasks.map((task, index) => (
                                                <TaskDot
                                                    key={task.id}
                                                    status={task.status}
                                                    left={`${((index + 1) / (phase.tasks.length + 1)) * 100}%`}
                                                    title={`${task.title} — ${task.statusLabel}`}
                                                />
                                            ))}

                                            {canEdit && (
                                                <>
                                                    <Handle
                                                        side="left"
                                                        label={`Change when ${phase.title} starts`}
                                                        onPointerDown={(
                                                            event,
                                                        ) =>
                                                            beginDrag(
                                                                event,
                                                                phase,
                                                                'start',
                                                            )
                                                        }
                                                        onPointerMove={moveDrag}
                                                        onPointerUp={() =>
                                                            endDrag(phase)
                                                        }
                                                    />
                                                    <Handle
                                                        side="right"
                                                        label={`Change when ${phase.title} ends`}
                                                        onPointerDown={(
                                                            event,
                                                        ) =>
                                                            beginDrag(
                                                                event,
                                                                phase,
                                                                'end',
                                                            )
                                                        }
                                                        onPointerMove={moveDrag}
                                                        onPointerUp={() =>
                                                            endDrag(phase)
                                                        }
                                                    />
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </Row>
                            );
                        })}
                    </div>

                    {today >= from && today <= to && (
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns:
                                    '84px minmax(0, 1fr) 150px',
                                columnGap: 12,
                                marginTop: 6,
                            }}
                        >
                            <span />
                            <div style={{ position: 'relative', height: 14 }}>
                                <span
                                    style={{
                                        position: 'absolute',
                                        left: pct(today),
                                        transform: 'translateX(-50%)',
                                        fontSize: 10,
                                        color: MUTED(55),
                                    }}
                                >
                                    Today
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </Panel>
    );
}

function TimelineTitle({ canEdit }: { canEdit: boolean }) {
    return (
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 12 }}>
            <span
                style={{
                    fontSize: 13,
                    fontWeight: 600,
                    letterSpacing: '0.04em',
                    marginRight: 'auto',
                }}
            >
                TIMELINE
            </span>
            <span style={{ fontSize: 11.5, color: MUTED(55) }}>
                {canEdit
                    ? 'Drag a phase to adjust its dates'
                    : 'Planned by the student · read-only'}
            </span>
        </div>
    );
}

function Row({
    phase,
    canEdit,
    onEditDates,
    dates,
    agreed = null,
    children,
}: {
    phase: Phase;
    canEdit: boolean;
    onEditDates: (phase: Phase) => void;
    dates: string;
    agreed?: string | null;
    children: React.ReactNode;
}) {
    return (
        <>
            <span
                style={{
                    fontSize: 12.5,
                    color:
                        phase.state === 'in_progress'
                            ? 'var(--color-accent)'
                            : 'var(--color-text)',
                }}
            >
                {phase.title}
            </span>

            {children}

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'flex-end',
                    gap: 6,
                }}
            >
                <div style={{ textAlign: 'right', lineHeight: 1.25 }}>
                    <div style={{ fontSize: 11.5, color: MUTED(65) }}>
                        {dates}
                    </div>
                    {agreed && (
                        <div style={{ fontSize: 10, color: MUTED(45) }}>
                            {agreed}
                        </div>
                    )}
                </div>
                {canEdit && (
                    <Btn
                        icon
                        variant="ghost"
                        aria-label={`Edit dates for ${phase.title}`}
                        onClick={() => onEditDates(phase)}
                        style={{ width: 28, height: 28 }}
                    >
                        <PencilSimpleIcon />
                    </Btn>
                )}
            </div>
        </>
    );
}

function TaskDot({
    status,
    left,
    title,
}: {
    status: TaskStatus;
    left: string;
    title: string;
}) {
    return (
        <span
            title={title}
            style={{
                position: 'absolute',
                left,
                top: '50%',
                width: 8,
                height: 8,
                borderRadius: '50%',
                transform: 'translate(-50%, -50%)',
                background:
                    status === 'verified'
                        ? 'var(--color-accent)'
                        : status === 'submitted'
                          ? 'var(--color-bg)'
                          : MUTED(35),
                border:
                    status === 'submitted'
                        ? '2px solid var(--color-accent)'
                        : 'none',
                boxSizing: 'border-box',
            }}
        />
    );
}

function Handle({
    side,
    label,
    ...events
}: {
    side: 'left' | 'right';
    label: string;
    onPointerDown: (event: ReactPointerEvent<HTMLElement>) => void;
    onPointerMove: (event: ReactPointerEvent<HTMLElement>) => void;
    onPointerUp: () => void;
}) {
    return (
        <span
            aria-label={label}
            {...events}
            style={{
                position: 'absolute',
                top: 0,
                bottom: 0,
                [side]: 0,
                width: 8,
                cursor: 'ew-resize',
                touchAction: 'none',
                zIndex: 3,
            }}
        />
    );
}
