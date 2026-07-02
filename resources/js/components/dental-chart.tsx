import { useState } from 'react';
import { getToothType, LOWER_TEETH, UPPER_TEETH } from '@/lib/teeth';

interface DentalChartProps {
    selectedTeeth: number[];
    onSelectionChange: (teeth: number[]) => void;
    disabled?: boolean;
}

// Quick-select jaws are the same FDI arrays, aliased for readability.
const UPPER_JAW = UPPER_TEETH;
const LOWER_JAW = LOWER_TEETH;

// Tooth shape path generator - creates a realistic tooth outline
function getToothPath(toothNumber: number): string {
    const type = getToothType(toothNumber);

    if (type === 'molar') {
        return 'M -14 -18 C -14 -20 -12 -22 -8 -22 L 8 -22 C 12 -22 14 -20 14 -18 L 14 14 C 14 18 10 22 6 22 L -6 22 C -10 22 -14 18 -14 14 Z';
    }
    if (type === 'premolar') {
        return 'M -11 -18 C -11 -20 -9 -22 -6 -22 L 6 -22 C 9 -22 11 -20 11 -18 L 11 14 C 11 18 8 22 4 22 L -4 22 C -8 22 -11 18 -11 14 Z';
    }
    if (type === 'canine') {
        return 'M -10 -16 C -10 -20 -8 -24 -4 -24 L 4 -24 C 8 -24 10 -20 10 -16 L 10 14 C 10 18 7 20 4 20 L -4 20 C -7 20 -10 18 -10 14 Z';
    }
    // Incisors
    return 'M -9 -16 C -9 -18 -7 -20 -5 -20 L 5 -20 C 7 -20 9 -18 9 -16 L 9 14 C 9 17 7 19 4 19 L -4 19 C -7 19 -9 17 -9 14 Z';
}

// Root path for upper teeth (roots go up)
function getUpperRootPath(toothNumber: number): string {
    const type = getToothType(toothNumber);

    if (type === 'molar') {
        return 'M -8 -22 C -10 -32 -12 -42 -10 -48 M 0 -22 C 0 -32 0 -42 0 -48 M 8 -22 C 10 -32 12 -42 10 -48';
    }
    if (type === 'premolar') {
        return 'M -4 -22 C -6 -32 -6 -42 -4 -46 M 4 -22 C 6 -32 6 -42 4 -46';
    }
    return 'M 0 -20 C 0 -30 -1 -40 0 -46';
}

// Root path for lower teeth (roots go down)
function getLowerRootPath(toothNumber: number): string {
    const type = getToothType(toothNumber);

    if (type === 'molar') {
        return 'M -6 22 C -8 32 -8 40 -6 46 M 6 22 C 8 32 8 40 6 46';
    }
    if (type === 'premolar') {
        return 'M 0 22 C -1 32 -1 40 0 46';
    }
    return 'M 0 19 C 0 30 -1 38 0 44';
}

export default function DentalChart({
    selectedTeeth,
    onSelectionChange,
    disabled = false,
}: DentalChartProps) {
    const [hoveredTooth, setHoveredTooth] = useState<number | null>(null);

    const toggleTooth = (toothNumber: number) => {
        if (disabled) return;
        if (selectedTeeth.includes(toothNumber)) {
            onSelectionChange(selectedTeeth.filter((t) => t !== toothNumber));
        } else {
            onSelectionChange(
                [...selectedTeeth, toothNumber].sort((a, b) => a - b),
            );
        }
    };

    const toothSpacing = 38;
    const startX = 32;
    const upperY = 75;
    const lowerY = 175;

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <label className="text-sm font-medium">الأسنان</label>
                {selectedTeeth.length > 0 && (
                    <button
                        type="button"
                        onClick={() => onSelectionChange([])}
                        className="text-xs text-muted-foreground transition-colors hover:text-foreground"
                    >
                        مسح الكل
                    </button>
                )}
            </div>

            <div className="rounded-xl border bg-card p-4 transition-colors">
                {/* Quick select buttons */}
                <div className="mb-3 flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        onClick={() => {
                            if (disabled) return;
                            const allSelected = UPPER_JAW.every((t) =>
                                selectedTeeth.includes(t),
                            );
                            if (allSelected) {
                                onSelectionChange(
                                    selectedTeeth.filter(
                                        (t) => !UPPER_JAW.includes(t),
                                    ),
                                );
                            } else {
                                onSelectionChange(
                                    [
                                        ...new Set([
                                            ...selectedTeeth,
                                            ...UPPER_JAW,
                                        ]),
                                    ].sort((a, b) => a - b),
                                );
                            }
                        }}
                        className="rounded-md border bg-background px-2 py-0.5 text-xs transition-colors hover:bg-accent"
                        disabled={disabled}
                    >
                        الفك العلوي
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            if (disabled) return;
                            const allSelected = LOWER_JAW.every((t) =>
                                selectedTeeth.includes(t),
                            );
                            if (allSelected) {
                                onSelectionChange(
                                    selectedTeeth.filter(
                                        (t) => !LOWER_JAW.includes(t),
                                    ),
                                );
                            } else {
                                onSelectionChange(
                                    [
                                        ...new Set([
                                            ...selectedTeeth,
                                            ...LOWER_JAW,
                                        ]),
                                    ].sort((a, b) => a - b),
                                );
                            }
                        }}
                        className="rounded-md border bg-background px-2 py-0.5 text-xs transition-colors hover:bg-accent"
                        disabled={disabled}
                    >
                        الفك السفلي
                    </button>
                </div>

                <svg
                    viewBox="0 0 640 260"
                    className="mx-auto w-full max-w-2xl"
                    style={{ minHeight: '180px' }}
                >
                    {/* Midline */}
                    <line
                        x1="320"
                        y1="10"
                        x2="320"
                        y2="250"
                        stroke="currentColor"
                        strokeOpacity="0.1"
                        strokeWidth="1"
                        strokeDasharray="4 4"
                    />

                    {/* Jaw separator line */}
                    <line
                        x1="20"
                        y1="125"
                        x2="620"
                        y2="125"
                        stroke="currentColor"
                        strokeOpacity="0.15"
                        strokeWidth="1.5"
                    />

                    {/* Labels */}
                    <text
                        x="16"
                        y="70"
                        fontSize="9"
                        fill="currentColor"
                        opacity="0.3"
                        textAnchor="end"
                        className="select-none"
                    >
                        R
                    </text>
                    <text
                        x="624"
                        y="70"
                        fontSize="9"
                        fill="currentColor"
                        opacity="0.3"
                        textAnchor="start"
                        className="select-none"
                    >
                        L
                    </text>
                    <text
                        x="16"
                        y="185"
                        fontSize="9"
                        fill="currentColor"
                        opacity="0.3"
                        textAnchor="end"
                        className="select-none"
                    >
                        R
                    </text>
                    <text
                        x="624"
                        y="185"
                        fontSize="9"
                        fill="currentColor"
                        opacity="0.3"
                        textAnchor="start"
                        className="select-none"
                    >
                        L
                    </text>

                    {/* Upper teeth */}
                    {UPPER_TEETH.map((tooth, i) => {
                        const x = startX + i * toothSpacing;
                        const isSelected = selectedTeeth.includes(tooth);
                        const isHovered = hoveredTooth === tooth;

                        return (
                            <g
                                key={tooth}
                                transform={`translate(${x}, ${upperY})`}
                                onClick={() => toggleTooth(tooth)}
                                onMouseEnter={() => setHoveredTooth(tooth)}
                                onMouseLeave={() => setHoveredTooth(null)}
                                className={
                                    disabled
                                        ? 'cursor-default'
                                        : 'cursor-pointer'
                                }
                                role="button"
                                tabIndex={disabled ? -1 : 0}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        toggleTooth(tooth);
                                    }
                                }}
                                aria-label={`سن ${tooth}`}
                                aria-pressed={isSelected}
                            >
                                {/* Root */}
                                <path
                                    d={getUpperRootPath(tooth)}
                                    fill="none"
                                    stroke={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    strokeOpacity={isSelected ? 0.6 : 0.2}
                                    strokeWidth="1.5"
                                    className="transition-all duration-200"
                                />

                                {/* Tooth body */}
                                <path
                                    d={getToothPath(tooth)}
                                    fill={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : isHovered
                                              ? 'hsl(var(--accent))'
                                              : 'hsl(var(--muted))'
                                    }
                                    fillOpacity={
                                        isSelected
                                            ? 0.25
                                            : isHovered
                                              ? 0.6
                                              : 0.4
                                    }
                                    stroke={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    strokeOpacity={
                                        isSelected ? 1 : isHovered ? 0.6 : 0.3
                                    }
                                    strokeWidth={isSelected ? 2 : 1.5}
                                    className="transition-all duration-200"
                                />

                                {/* Inner detail - occlusal surface hint */}
                                <ellipse
                                    cx="0"
                                    cy="-2"
                                    rx="5"
                                    ry="4"
                                    fill="none"
                                    stroke={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    strokeOpacity={isSelected ? 0.4 : 0.1}
                                    strokeWidth="0.75"
                                    className="transition-all duration-200"
                                />

                                {/* Tooth number */}
                                <text
                                    x="0"
                                    y="38"
                                    textAnchor="middle"
                                    fontSize="10"
                                    fontWeight={isSelected ? '700' : '500'}
                                    fill={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    opacity={
                                        isSelected ? 1 : isHovered ? 0.8 : 0.5
                                    }
                                    className="transition-all duration-200 select-none"
                                >
                                    {tooth}
                                </text>

                                {/* Selection indicator dot */}
                                {isSelected && (
                                    <circle
                                        cx="0"
                                        cy="0"
                                        r="3"
                                        fill="hsl(var(--primary))"
                                        opacity="0.8"
                                        className="animate-in duration-200 zoom-in"
                                    />
                                )}
                            </g>
                        );
                    })}

                    {/* Lower teeth */}
                    {LOWER_TEETH.map((tooth, i) => {
                        const x = startX + i * toothSpacing;
                        const isSelected = selectedTeeth.includes(tooth);
                        const isHovered = hoveredTooth === tooth;

                        return (
                            <g
                                key={tooth}
                                transform={`translate(${x}, ${lowerY})`}
                                onClick={() => toggleTooth(tooth)}
                                onMouseEnter={() => setHoveredTooth(tooth)}
                                onMouseLeave={() => setHoveredTooth(null)}
                                className={
                                    disabled
                                        ? 'cursor-default'
                                        : 'cursor-pointer'
                                }
                                role="button"
                                tabIndex={disabled ? -1 : 0}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        toggleTooth(tooth);
                                    }
                                }}
                                aria-label={`سن ${tooth}`}
                                aria-pressed={isSelected}
                            >
                                {/* Root */}
                                <path
                                    d={getLowerRootPath(tooth)}
                                    fill="none"
                                    stroke={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    strokeOpacity={isSelected ? 0.6 : 0.2}
                                    strokeWidth="1.5"
                                    className="transition-all duration-200"
                                />

                                {/* Tooth body */}
                                <path
                                    d={getToothPath(tooth)}
                                    fill={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : isHovered
                                              ? 'hsl(var(--accent))'
                                              : 'hsl(var(--muted))'
                                    }
                                    fillOpacity={
                                        isSelected
                                            ? 0.25
                                            : isHovered
                                              ? 0.6
                                              : 0.4
                                    }
                                    stroke={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    strokeOpacity={
                                        isSelected ? 1 : isHovered ? 0.6 : 0.3
                                    }
                                    strokeWidth={isSelected ? 2 : 1.5}
                                    className="transition-all duration-200"
                                />

                                {/* Inner detail */}
                                <ellipse
                                    cx="0"
                                    cy="2"
                                    rx="5"
                                    ry="4"
                                    fill="none"
                                    stroke={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    strokeOpacity={isSelected ? 0.4 : 0.1}
                                    strokeWidth="0.75"
                                    className="transition-all duration-200"
                                />

                                {/* Tooth number */}
                                <text
                                    x="0"
                                    y="-30"
                                    textAnchor="middle"
                                    fontSize="10"
                                    fontWeight={isSelected ? '700' : '500'}
                                    fill={
                                        isSelected
                                            ? 'hsl(var(--primary))'
                                            : 'currentColor'
                                    }
                                    opacity={
                                        isSelected ? 1 : isHovered ? 0.8 : 0.5
                                    }
                                    className="transition-all duration-200 select-none"
                                >
                                    {tooth}
                                </text>

                                {/* Selection indicator dot */}
                                {isSelected && (
                                    <circle
                                        cx="0"
                                        cy="0"
                                        r="3"
                                        fill="hsl(var(--primary))"
                                        opacity="0.8"
                                        className="animate-in duration-200 zoom-in"
                                    />
                                )}
                            </g>
                        );
                    })}
                </svg>

                {/* Selected teeth summary */}
                {selectedTeeth.length > 0 && (
                    <div className="mt-3 border-t pt-3">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="ml-1 text-xs text-muted-foreground">
                                الأسنان المختارة:
                            </span>
                            {selectedTeeth.map((tooth) => (
                                <span
                                    key={tooth}
                                    className="inline-flex h-6 min-w-[24px] items-center justify-center rounded-md border border-primary/20 bg-primary/15 px-1.5 text-xs font-semibold text-primary"
                                >
                                    {tooth}
                                </span>
                            ))}
                            <span className="mr-auto text-xs text-muted-foreground">
                                ({selectedTeeth.length}{' '}
                                {selectedTeeth.length === 1 ? 'سن' : 'أسنان'})
                            </span>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
