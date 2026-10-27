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
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
      },
      fontFamily: {
        // Poppins ne couvre pas l'arabe : les glyphes AR retombent sur Cairo
        // automatiquement — une seule pile pour les deux langues.
        sans: ['Poppins', 'Cairo', 'system-ui', 'sans-serif'],
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
