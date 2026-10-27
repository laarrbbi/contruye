<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * A Spanish mobile as dialled at home (6 or 7, then eight digits), or any
     * number in international form: "+", a country code (never starting with
     * 0), eight to fifteen digits in all, as E.164 allows — buyers living
     * abroad can open an account too.
     *
     * Spain's own code is still held to the mobile rule: "+34 0612…" or a
     * landline after +34 is refused rather than stored as a number the owner
     * cannot WhatsApp. RegisterPage.tsx carries the same pattern; the D flag
     * makes "$" the end of the string, as it already is in JavaScript.
     */
    private const PHONE_PATTERN = '/^(?:[67]\d{8}|\+34[67]\d{8}|\+(?!34)[1-9]\d{7,14})$/D';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_PATTERN],
            'password' => ['required', 'confirmed', Password::defaults()],
            'locale' => ['sometimes', Rule::in(config('locales.supported'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Invalid number: Spanish mobile (9 digits starting with 6 or 7) or international number starting with +'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->phone)) {
            $this->merge(['phone' => self::normalisePhone($this->phone)]);
        }
    }

    /**
     * The number as it is checked and stored: "612345678", "+33612345678".
     *
     * RegisterPage.tsx runs the very same steps before its own check. If the
     * two drift, the form lets a number through and the API sends it back
     * refused, or the other way round.
     */
    private static function normalisePhone(string $raw): string
    {
        // Separators, listed one by one because \s is not the same set here
        // (with /u) and in JavaScript: ASCII whitespace; the no-break and other
        // Unicode spaces of a number pasted from a web page; the invisible
        // direction marks a number copied out of a right-to-left chat arrives
        // wrapped in (U+061C, U+200E, U+202A…); dots and dashes. Invalid UTF-8
        // is left as typed, for the rule to refuse.
        $phone = preg_replace(
            '/[\t\n\x{0B}\f\r \x{A0}\x{061C}\x{1680}\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}\x{2066}-\x{2069}\x{3000}\x{FEFF}.\-]/u',
            '',
            $raw,
        ) ?? $raw;

        // "+33 (0)6 …": the bracketed zero is the one not dialled from abroad.
        // Kept, it would make +330612345678, a number that rings nowhere and
        // that the rule refuses, when the buyer wrote a good one.
        $phone = str_replace(['(0)', '(', ')'], '', $phone);

        // "00" is the prefix most of the world dials for "+", and no Spanish
        // number starts with 0, so reading it as "+" never takes a home number.
        return preg_replace('/^00/', '+', $phone) ?? $phone;
    }
}
