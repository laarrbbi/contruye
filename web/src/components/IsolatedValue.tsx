import { useTranslation } from 'react-i18next'

// Stands in for the value while the sentence is translated. A private-use
// character: it never occurs in a translation.
const MARK = String.fromCharCode(0xe000)

/**
 * A translated sentence with a value the owner typed set in its own isolate
 * (<bdi>). Inside an Arabic sentence the browser otherwise reorders a value
 * written left to right: "16/00-1234567 B 26" came out as
 * "1234567-16/00 B 26", a capital of "1 000 000 DA" as "DA 000 000 1".
 *
 * dir="ltr" for identifiers and amounts, which read left to right in every
 * language and stay on one line ("1 000 000" never wraps before "DA").
 * Without it the value takes the direction of its first letter: a French
 * module title in "الوحدة 3 — L'agrément (LE module bloquant)".
 *
 * `values` fills the sentence's other placeholders, as plain text.
 */
export function IsolatedValue({
  i18nKey,
  name,
  value,
  dir,
  values,
}: {
  i18nKey: string
  name: string
  value: string
  dir?: 'ltr'
  values?: Record<string, string | number>
}) {
  const { t } = useTranslation()
  const [before, after = ''] = t(i18nKey, { ...values, [name]: MARK }).split(MARK)

  return (
    <>
      {before}
      <bdi dir={dir} className={dir === 'ltr' ? 'whitespace-nowrap' : undefined}>
        {value}
      </bdi>
      {after}
    </>
  )
}
