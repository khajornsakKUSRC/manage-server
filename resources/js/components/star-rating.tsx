import { Star } from 'lucide-react';

interface StarRatingProps {
    value: number;
    onChange?: (value: number) => void;
    readOnly?: boolean;
    size?: number;
}

/**
 * 5-star scale, highest (5) to lowest (1). Interactive when `onChange` is
 * given and `readOnly` is false; otherwise a static display that rounds
 * to the nearest whole star.
 */
export function StarRating({
    value,
    onChange,
    readOnly = false,
    size = 20,
}: StarRatingProps) {
    const interactive = !readOnly && typeof onChange === 'function';
    const box = { width: size, height: size };

    return (
        <span className="inline-flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map((n) => {
                const filled = interactive
                    ? n <= value
                    : n <= Math.round(value);
                const cls = filled
                    ? 'fill-amber-400 text-amber-400'
                    : 'text-muted-foreground/30';

                if (!interactive) {
                    return <Star key={n} style={box} className={cls} />;
                }

                return (
                    <button
                        key={n}
                        type="button"
                        onClick={() => onChange?.(n)}
                        className="transition-transform hover:scale-110"
                        aria-label={`${n} star${n > 1 ? 's' : ''}`}
                    >
                        <Star style={box} className={cls} />
                    </button>
                );
            })}
        </span>
    );
}
