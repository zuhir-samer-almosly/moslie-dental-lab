const TASHKEEL = /[ً-ْـ]/g;

/**
 * Folds the Arabic spellings that differ only by hamza/alef form so typing
 * "احمد" still finds a dentist stored as "أحمد". Latin text is lowercased so
 * the same helper covers account codes and mixed-script names.
 *
 * Mirrored server-side by `App\Support\WorkTypes::normalise` — the two must
 * agree on what counts as the same work type.
 */
export function normalizeArabic(text: string): string {
    return text
        .replace(TASHKEEL, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}
