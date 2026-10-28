import { useTranslation } from 'react-i18next'

// Stands in for the value while the sentence is translated. A private-use
// character: it never occurs in a translation.
const MARK = String.fromCharCode(0xe000)

/**
 * A translated sentence with a value the owner typed set in its own isolate
 * (<bdi>). Next to text in another direction the browser otherwise reorders
 * a value written left to right: an identifier "B-12345678" can come out
 * scrambled, a capital of "3.000 €" with its symbol on the wrong side.
 *
 * dir="ltr" for identifiers and amounts, which read left to right in every
 * language and stay on one line ("3.000" never wraps before "€").
 * Without it the value takes the direction of its first letter, which is
 * what a module title or a name typed in any script needs.
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
