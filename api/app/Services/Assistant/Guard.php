<?php

namespace App\Services\Assistant;

/**
 * The guard on both sides of the model.
 *
 * Before (question()): the student's text is cleaned (no invisible or
 * direction-changing characters, no markup that could close the tags the
 * question travels in) and read for the plain attempts: overriding the
 * instructions, extracting the prompt, other people's data or the keys.
 * Those are answered here, without calling the model (no cost, no
 * credits). Subtler attempts reach the model, whose prompt treats the
 * question and every document as data; they are flagged in the trace.
 *
 * After (answer()): an answer is stopped if it carries the prompt's canary,
 * a key, or a passage of the instructions; e-mail addresses the course does
 * not contain are hidden, and a link to a site that no document or tool
 * result named loses its address (the model cannot point a student to a
 * site the course never gave).
 */
final class Guard
{
    /** Attempts answered without the model: [flag, pattern]. */
    private const BLOCK = [
        // Override the instructions.
        ['injection', '/\b(ignore|disregard|forget|override)\b.{0,40}\b(previous|prior|above|all|your|the|system)\b.{0,30}\b(instructions?|prompts?|rules|guidelines|directives)\b/iu'],
        ['injection', '/\b(ignora|olvida|omite|descarta|salta(te)?)\b.{0,40}\b(instrucciones|indicaciones|reglas|pautas|directrices|prompt)\b/iu'],
        ['injection', '/\b(ignore|oublie)[sz]?\b.{0,40}\b(instructions|consignes|règles)\b/iu'],
        ['injection', '/(игнорируй|забудь|ігноруй|забудь)\W.{0,40}(инструкци|правил|інструкці|правил)/iu'],
        ['injection', '/(تجاهل|انس)\s.{0,40}(التعليمات|القواعد)/u'],
        ['injection', '/\b(developer|god|jailbreak|DAN)\s+mode\b|\bmodo\s+(desarrollador|dios|sin\s+restricciones)\b|\bjailbreak\b/iu'],
        // Extract the instructions.
        ['prompt', '/\b(system|initial|hidden|original)\s+(prompt|instructions|message)\b/iu'],
        ['prompt', '/\b(prompt|mensaje|instrucciones)\s+(del\s+sistema|de\s+sistema|inicial(es)?|ocult[ao]s?|original(es)?)\b/iu'],
        ['prompt', '/\b((mu[eé]stra|ens[eé][nñ]a|revela|repite|copia|escribe|imprime|pasa)(me)?|dame|dime|show|reveal|repeat|print|output|tell\s+me)\b.{0,30}\b(tus|your|las|the)\s+(instrucciones|instructions|reglas|rules|configuraci[oó]n|configuration)\b/iu'],
        ['prompt', '/\b(prompt\s+syst[eè]me|instructions\s+syst[eè]me)\b|системн\w+\s+промпт|системн\w+\s+інструкц/iu'],
        // Keys and secrets.
        ['secrets', '/\b(api[\s_-]?key|clave\s+(de\s+(la\s+)?)?api|access\s+token|secret\s+key|anthropic[\s_-]?(api|key)|sk-ant)\b/iu'],
        ['secrets', '/\b(\.env|app_key|db_password|stripe[\s_-]?(secret|key))\b/iu'],
        // Other people's data, the administration.
        ['accounts', '/\b(otr[oa]s?\s+(alumn[oa]s?|usuari[oa]s?|cuentas|estudiantes)|other\s+(students?|users?|accounts?)|autres\s+(élèves|utilisateurs|comptes))\b.{0,60}\b(datos|correos?|e-?mails?|emails?|tel[eé]fonos?|contraseñas?|passwords?|pedidos|orders?|facturas|invoices?|nombres|names|cuentas|accounts?|data|donn[ée]es|mots?\s+de\s+passe)\b/iu'],
        ['accounts', '/\b(lista|listado|list|base\s+de\s+datos|database|dump)\b.{0,30}\b(de\s+)?(usuarios|alumnos|clientes|estudiantes|users|students|customers|admins?|administradores)\b/iu'],
        ['accounts', '/\b(contraseña|password|mot\s+de\s+passe|credenciales|credentials)\b.{0,40}\b(admin(istrador)?|de\s+otr[oa]|del\s+usuario|of\s+(the\s+)?(admin|user|another))\b/iu'],
        ['accounts', '/\b(SELECT|UNION|INSERT|UPDATE|DELETE|DROP)\b\s.{0,40}\b(FROM|INTO|TABLE|SET)\b/u'],
    ];

    /** Signs worth a flag in the trace, not a refusal. */
    private const FLAG = [
        ['roleplay', '/\b(act[uú]a\s+como|finge\s+(que\s+)?(eres|ser)|pretend\s+(to\s+be|you\s+are)|you\s+are\s+now|ahora\s+eres|from\s+now\s+on)\b/iu'],
        ['encoded', '/[A-Za-z0-9+\/]{120,}={0,2}/'],
        ['exam', '/\b(respuesta|soluci[oó]n|answer)\b.{0,40}\b(examen|cuestionario|quiz|test\s+final|exam)\b/iu'],
    ];

    /** Fake turn and role markers, in the shapes models were trained on. */
    private const ROLE_MARKERS = '/^\s*(system|assistant|human|user|developer|sistema|asistente)\s*:|<\|[a-z_]+\|>|\[\/?(INST|SYS)\]|<<\/?SYS>>/imu';

    /**
     * @return array{text: string, block: ?string, flags: list<string>}
     */
    public function question(string $raw): array
    {
        $flags = [];
        $text = $raw;

        $plain = Tokenizer::plain($text);
        if ($plain !== $text && preg_match('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', $text)) {
            $flags[] = 'invisible';
        }
        // NFKC folds look-alike letters (ｉｇｎｏｒｅ → ignore) before the patterns read the text.
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text) ?? $text;
        $text = preg_replace('/[^\P{C}\n\t]/u', '', $text) ?? $text;
        $text = trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)) ?? $text);

        if (preg_match(self::ROLE_MARKERS, $text)) {
            $flags[] = 'role_markers';
        }
        if (preg_match('/<\/?\s*(pregunta|documento|documentos|alumno|system|instrucciones)\b/iu', $text)) {
            $flags[] = 'markup';
        }

        $folded = Tokenizer::plain($text);
        $block = null;
        foreach (self::BLOCK as [$flag, $pattern]) {
            if (preg_match($pattern, $text) || preg_match($pattern, $folded)) {
                $block = $flag;
                $flags[] = $flag;
                break;
            }
        }
        foreach (self::FLAG as [$flag, $pattern]) {
            if (preg_match($pattern, $text)) {
                $flags[] = $flag;
            }
        }

        // A tag in the question is text, never structure: < and > go in as their look-alikes.
        $text = strtr($text, ['<' => '‹', '>' => '›']);

        return ['text' => $text, 'block' => $block, 'flags' => array_values(array_unique($flags))];
    }

    /**
     * @param  list<string>  $context  every text the model was given for this answer: documents and tool results
     * @param  list<string>  $instructions  the passages of the instructions that must never come out
     * @return array{text: string, block: ?string, flags: list<string>}
     */
    public function answer(string $text, array $context, array $instructions = []): array
    {
        $flags = [];

        if (str_contains($text, Prompt::canary())) {
            return ['text' => '', 'block' => 'canary', 'flags' => ['canary']];
        }
        $key = (string) config('assistant.anthropic.api_key');
        if (preg_match('/sk-ant-[A-Za-z0-9_\-]{8,}/', $text) || ($key !== '' && str_contains($text, substr($key, 0, 24)))) {
            return ['text' => '', 'block' => 'secret', 'flags' => ['secret']];
        }
        $folded = self::fold($text);
        foreach ($instructions as $passage) {
            if (mb_strlen($passage) >= 60 && str_contains($folded, self::fold($passage))) {
                return ['text' => '', 'block' => 'prompt_leak', 'flags' => ['prompt_leak']];
            }
        }

        $haystack = implode("\n", $context);

        // E-mail addresses only as the course writes them.
        $text = (string) preg_replace_callback('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', function (array $m) use ($haystack, &$flags) {
            if (stripos($haystack, $m[0]) !== false) {
                return $m[0];
            }
            $flags[] = 'email';

            return '[…]';
        }, $text);

        // Links only to sites the documents or the tools named, or inside the app.
        $hosts = $this->hosts($haystack);
        $text = (string) preg_replace_callback('/\[([^\]]{1,200})\]\(\s*([^)\s]+)(?:\s+"[^"]*")?\s*\)/u', function (array $m) use ($hosts, &$flags) {
            if ($this->allowedUrl($m[2], $hosts)) {
                return $m[0];
            }
            $flags[] = 'link';

            return $m[1];
        }, $text);
        $text = (string) preg_replace_callback('/(?<![\(\[<"\w])(https?:\/\/|www\.)[^\s<>()\[\]]+/iu', function (array $m) use ($hosts, &$flags) {
            $url = rtrim($m[0], '.,;:!?»”');
            $tail = substr($m[0], strlen($url));
            if ($this->allowedUrl($url, $hosts)) {
                return $m[0];
            }
            $flags[] = 'link';
            $host = (string) parse_url(str_starts_with(strtolower($url), 'www.') ? 'https://'.$url : $url, PHP_URL_HOST);

            return '`'.preg_replace('/^www\./i', '', $host).'`'.$tail;
        }, $text);

        return ['text' => $text, 'block' => null, 'flags' => array_values(array_unique($flags))];
    }

    /**
     * The instructions' sentences, as answer() looks for them.
     *
     * @return list<string>
     */
    public static function passages(string $template): array
    {
        $sentences = preg_split('/(?<=[.:;])\s+|\n+/u', $template) ?: [];

        return array_values(array_filter(
            array_map(fn (string $s) => trim(preg_replace('/\{\{[a-z]+\}\}/', '', $s) ?? $s, " -#\t"), $sentences),
            fn (string $s) => mb_strlen($s) >= 60,
        ));
    }

    /** @param list<string> $hosts */
    private function allowedUrl(string $url, array $hosts): bool
    {
        $url = trim($url, '<>');
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        if (str_starts_with(strtolower($url), 'www.')) {
            $url = 'https://'.$url;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        return in_array(preg_replace('/^www\./', '', $host), $hosts, true);
    }

    /** @return list<string> */
    private function hosts(string $haystack): array
    {
        $hosts = [];
        preg_match_all('/(?:https?:\/\/|www\.)([a-z0-9.\-]+\.[a-z]{2,})/i', $haystack, $m);
        foreach ($m[1] as $host) {
            $hosts[] = preg_replace('/^www\./', '', strtolower($host));
        }
        // The official addresses the course already links: its laws, its regions' pages, its lessons' sites.
        $official = [config('legal', []), config('lesson_resources', [])];
        array_walk_recursive($official, function ($value) use (&$hosts) {
            if (is_string($value) && preg_match('/^https:\/\//', $value) && ($host = parse_url($value, PHP_URL_HOST))) {
                $hosts[] = preg_replace('/^www\./', '', strtolower($host));
            }
        });

        return array_values(array_unique($hosts));
    }

    private static function fold(string $text): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(Tokenizer::plain($text))) ?? $text;
    }
}
