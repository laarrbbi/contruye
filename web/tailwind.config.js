/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['class'],
  // A touch tap fires :hover and leaves it stuck until the next tap. Gate
  // every hover: utility behind a real pointer.
  future: { hoverOnlyWhenSupported: true },
  // The server-rendered public pages use the same classes (api/resources/views/seo).
  content: ['./index.html', './src/**/*.{ts,tsx}', '../api/resources/views/seo/**/*.blade.php'],
  theme: {
    container: {
      center: true,
      padding: '1rem',
      screens: { '2xl': '1200px' },
    },
    extend: {
      colors: {
        border: 'hsl(var(--border))',
        input: 'hsl(var(--input))',
        ring: 'hsl(var(--ring))',
        background: 'hsl(var(--background))',
        foreground: 'hsl(var(--foreground))',
        primary: {
          DEFAULT: 'hsl(var(--primary))',
          foreground: 'hsl(var(--primary-foreground))',
        },
        secondary: {
          DEFAULT: 'hsl(var(--secondary))',
          foreground: 'hsl(var(--secondary-foreground))',
        },
        destructive: {
          DEFAULT: 'hsl(var(--destructive))',
          foreground: 'hsl(var(--destructive-foreground))',
        },
        muted: {
          DEFAULT: 'hsl(var(--muted))',
          foreground: 'hsl(var(--muted-foreground))',
        },
        accent: {
          DEFAULT: 'hsl(var(--accent))',
          foreground: 'hsl(var(--accent-foreground))',
        },
        card: {
          DEFAULT: 'hsl(var(--card))',
          foreground: 'hsl(var(--card-foreground))',
        },
        warning: {
          DEFAULT: 'hsl(var(--warning))',
          foreground: 'hsl(var(--warning-foreground))',
        },
        success: {
          DEFAULT: 'hsl(var(--success))',
          foreground: 'hsl(var(--success-foreground))',
        },
        // The sales page's palette (index.css). Named as in the plan.
        nuit: { 950: 'hsl(var(--nuit-950))', 900: 'hsl(var(--nuit-900))' },
        // Direction « Du croquis à la réalité » (docs/redesign-plan.md, 16).
        // Night: the real, the site, the proof. Paper: the project, the plan.
        sombre: { 950: '#070F1C', 900: '#0B1A2E', 800: '#12253F', 700: '#1F3A5F' },
        creme: { 100: '#F6F2EA', 200: '#EFE9DE' },
        plume: '#141A22', // ink on paper: 15.6:1 on creme-100
        or: { DEFAULT: '#E8A33D', fonce: '#8F5A0E' }, // fonce: 5.0:1 on creme-100, for text
        electrique: '#3B6FD8',
        graphite: '#5B6470', // pencil lines; 5.3:1 on creme-100 for small notes
        marine: { 700: 'hsl(var(--marine-700))', 600: 'hsl(var(--marine-600))' },
        trait: 'hsl(var(--trait-nuit))',
        blanc: 'hsl(var(--blanc))',
        papier: { 100: 'hsl(var(--papier-100))', 50: 'hsl(var(--papier-50))' },
        encre: 'hsl(var(--encre))',
        beton: {
          200: 'hsl(var(--beton-200))',
          500: 'hsl(var(--beton-500))',
          600: 'hsl(var(--beton-600))',
        },
        ambre: {
          400: 'hsl(var(--ambre-400))',
          500: 'hsl(var(--ambre-500))',
          700: 'hsl(var(--ambre-700))',
        },
        revision: 'hsl(var(--rouge-revision))',
      },
      boxShadow: {
        // One light, from above. Three layers, tinted with the ink, never black.
        plate:
          '0 1px 1px hsl(var(--encre) / 0.05), 0 6px 12px -2px hsl(var(--encre) / 0.07), 0 22px 40px -12px hsl(var(--encre) / 0.14)',
        // A sheet of paper lifted off the night drawing.
        float:
          '0 2px 3px hsl(214 70% 4% / 0.3), 0 14px 24px -6px hsl(214 70% 4% / 0.45), 0 44px 72px -20px hsl(214 70% 4% / 0.6)',
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
      },
      fontFamily: {
        // Poppins covers the Latin languages (á, é, ñ, ü, ç, œ are in its
        // latin subset). It has no Cyrillic or Arabic: Russian and Ukrainian
        // fall back to Montserrat's Cyrillic, Arabic to Cairo, in the same
        // stack. Each subset is downloaded only when its script is on screen.
        sans: ['Poppins', 'Montserrat', 'Cairo', 'system-ui', 'sans-serif'],
      },
      transitionTimingFunction: {
        // Named to match the CSS variables in index.css.
        'out-strong': 'cubic-bezier(0.23, 1, 0.32, 1)',
        'in-out-strong': 'cubic-bezier(0.77, 0, 0.175, 1)',
        drawer: 'cubic-bezier(0.32, 0.72, 0, 1)',
      },
      keyframes: {
        'accordion-down': {
          from: { height: '0' },
          to: { height: 'var(--radix-accordion-content-height)' },
        },
        'accordion-up': {
          from: { height: 'var(--radix-accordion-content-height)' },
          to: { height: '0' },
        },
      },
      animation: {
        // Opens on the strong curve; closes faster — the system responding.
        // Height stays a keyframe because Radix measures it; the FAQ opens a
        // handful of times per visit, so the layout cost is acceptable.
        'accordion-down': 'accordion-down 200ms cubic-bezier(0.23, 1, 0.32, 1)',
        'accordion-up': 'accordion-up 150ms cubic-bezier(0.23, 1, 0.32, 1)',
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
}
