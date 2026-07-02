// FDI (ISO 3950) tooth-numbering helpers, shared by the interactive dental
// chart (entry) and the read-only odontogram (display).
//
// The first digit is the quadrant, the second the tooth position from the
// midline:
//   Upper right:  18, 17, 16, 15, 14, 13, 12, 11   (patient's right)
//   Upper left:   21, 22, 23, 24, 25, 26, 27, 28
//   Lower left:   31, 32, 33, 34, 35, 36, 37, 38
//   Lower right:  48, 47, 46, 45, 44, 43, 42, 41

// Each arch, ordered left-to-right as drawn: patient's right first, then the
// midline, then patient's left. The 8th/9th slots are the central incisors.
export const UPPER_TEETH = [
    18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28,
];
export const LOWER_TEETH = [
    48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38,
];

export type ToothType = 'molar' | 'premolar' | 'canine' | 'incisor';

// In FDI the last digit determines the tooth type:
// 1 = central incisor, 2 = lateral incisor, 3 = canine,
// 4 = first premolar, 5 = second premolar,
// 6 = first molar, 7 = second molar, 8 = third molar (wisdom).
export function getToothType(fdiNumber: number): ToothType {
    const lastDigit = fdiNumber % 10;
    if (lastDigit >= 6) return 'molar'; // 6, 7, 8
    if (lastDigit >= 4) return 'premolar'; // 4, 5
    if (lastDigit === 3) return 'canine'; // 3
    return 'incisor'; // 1, 2
}

export const TOOTH_TYPE_LABELS_AR: Record<ToothType, string> = {
    molar: 'رحى',
    premolar: 'ضاحك',
    canine: 'ناب',
    incisor: 'قاطع',
};
