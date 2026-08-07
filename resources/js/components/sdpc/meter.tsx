type MeterProps = {
    label: string;
    /** Percentage from 0-100. Values outside the range are clamped. */
    value: number;
    className?: string;
};

/**
 * The SDPC design's labelled progress bar — used for match scores, posting
 * completeness and profile completion.
 */
export default function Meter({ label, value, className }: MeterProps) {
    const percentage = Math.min(100, Math.max(0, Math.round(value)));

    return (
        <div className={className}>
            <div className="mb-1 flex justify-between text-[11.5px]">
                <span>{label}</span>
                <span className="text-primary">{percentage}%</span>
            </div>

            <div
                role="progressbar"
                aria-label={label}
                aria-valuenow={percentage}
                aria-valuemin={0}
                aria-valuemax={100}
                className="h-[5px] overflow-hidden rounded-[3px] bg-ink-800"
            >
                <div
                    className="h-full rounded-[3px] bg-primary transition-[width] duration-500"
                    style={{ width: `${percentage}%` }}
                />
            </div>
        </div>
    );
}
