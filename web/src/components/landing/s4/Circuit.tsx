import { useTranslation } from "react-i18next";
import {
  HardHat,
  LandPlot,
  Milestone,
  Plane,
  TriangleAlert,
  type LucideIcon,
} from "lucide-react";
import { Mots } from "@/components/landing/kit/pieces";
import { useScrollThrough, useVu, vars } from "@/components/landing/kit/tools";
import { BANDE, H2 } from "@/components/landing/kit/type";
import { usePrefersReducedMotion } from "@/hooks/usePrefersReducedMotion";
import { cn } from "@/lib/utils";

const PROFILES: { key: "p1" | "p2" | "p3" | "p4"; icon: LucideIcon }[] = [
  { key: "p1", icon: LandPlot },
  { key: "p2", icon: HardHat },
  { key: "p3", icon: Plane },
  { key: "p4", icon: Milestone },
];
const GATES = ["b1", "b2", "b3", "b4", "b5", "b6", "b7"] as const;
/**
 * Each costly mistake sits on the step its own wording names: the building
 * licence, the guarantee of the buyers' advance payments, the ten-year
 * insurance and its OCT, and the deed of new building and horizontal
 * division that closes the works.
 */
const MISTAKE_AT: Partial<Record<(typeof GATES)[number], string>> = {
  b1: "e1",
  b3: "e2",
  b4: "e3",
  b7: "e4",
};
/** Who it is not for, said as plainly as who it is for. */
const NOT_FOR = ["no1", "no2"] as const;

const number = (index: number) => String(index + 1).padStart(2, "0");

/**
 * S4, on paper: who this is for, then the real obstacle.
 *
 * Four starting points as four light cards. Then the administrative circuit
 * as a timeline: one straight line down the middle that fills with amber as
 * it is read, seven numbered stops that fill when the line reaches them, the
 * steps on either side in turn, and, where a mistake costs dearly, a small
 * clean note under the step. On a phone the line runs down the side.
 *
 * No duration and no amount: the copy gives none.
 */
export function Circuit() {
  const { t } = useTranslation();
  const still = usePrefersReducedMotion();
  const who = useVu<HTMLDivElement>();
  const maze = useVu<HTMLDivElement>();
  const cost = useVu<HTMLDivElement>();
  const steps = useVu<HTMLDivElement>(0.05);
  const rail = useScrollThrough<HTMLOListElement>(
    (p, node) => node.style.setProperty("--t", p.toFixed(3)),
    !still,
  );

  return (
    <section
      id="pour-qui"
      data-tone="paper"
      aria-labelledby="s4-titre"
      className="relative bg-creme-100 text-plume"
    >
      <div className={cn(BANDE, "!pt-10 lg:!pb-28 lg:!pt-16")}>
        {/* Who it is for: four light cards, each a number, a sign, a name and one sentence. */}
        <div ref={who}>
          <h2 id="s4-titre" className={cn(H2, "max-w-[20ch]")}>
            <Mots text={t("landing.forWhom.title")} />
          </h2>
          <ul className="mt-12 grid gap-4 sm:grid-cols-2 lg:mt-16 lg:grid-cols-4 lg:gap-5">
            {PROFILES.map(({ key, icon: Icon }, index) => (
              <li
                key={key}
                className={cn(
                  "entree group flex flex-col rounded-2xl bg-[#FCFAF6] p-6 ring-1 ring-plume/[0.07] lg:p-7",
                  "shadow-[0_1px_2px_rgb(31_58_95/0.06),0_12px_28px_-14px_rgb(31_58_95/0.22)]",
                  "transition-[transform,box-shadow] duration-300 ease-out-strong [@media(hover:hover)]:hover:-translate-y-1 [@media(hover:hover)]:hover:shadow-[0_1px_2px_rgb(31_58_95/0.06),0_22px_40px_-16px_rgb(31_58_95/0.3)]",
                )}
                style={vars({ "--d": `${250 + index * 100}ms` })}
              >
                <div className="flex items-center justify-between">
                  <span className="grid h-11 w-11 place-items-center rounded-xl bg-[rgb(232_163_61/0.14)] text-or-fonce">
                    <Icon
                      aria-hidden
                      className="h-[1.375rem] w-[1.375rem]"
                      strokeWidth={1.6}
                    />
                  </span>
                  <span
                    dir="ltr"
                    className="font-mono text-[0.8125rem] font-medium tracking-[0.14em] text-plume/40"
                  >
                    {number(index)}
                  </span>
                </div>
                <h3 className="mt-6 text-[1.1875rem] font-semibold leading-snug">
                  {t(`landing.forWhom.${key}Title`)}
                </h3>
                <p className="mt-2 text-[0.9375rem] leading-relaxed text-plume/70 rtl:leading-loose">
                  {t(`landing.forWhom.${key}Short`)}
                </p>
              </li>
            ))}
          </ul>
          {/* And who it is not for: two lines under the cards, no box. */}
          <div className="entree mt-8 lg:mt-10" style={vars({ "--d": "700ms" })}>
            <p className="etiquette text-plume/60">{t("landing.forWhom.noTitle")}</p>
            <ul className="mt-3 grid gap-x-10 gap-y-2 lg:grid-cols-2">
              {NOT_FOR.map((key) => (
                <li key={key} className="flex items-start gap-2.5 text-[0.9375rem] leading-relaxed text-plume/75 rtl:leading-loose">
                  <span aria-hidden className="mt-[0.6rem] h-px w-3 shrink-0 bg-plume/40" />
                  {t(`landing.forWhom.${key}`)}
                </li>
              ))}
            </ul>
          </div>
        </div>

        {/* The real obstacle, then the circuit as a timeline. */}
        <div
          ref={maze}
          className="mx-auto mt-24 max-w-[44rem] text-center lg:mt-36"
        >
          <h2 className={cn(H2, "mx-auto max-w-[20ch]")}>
            <Mots text={t("landing.maze.title")} />
          </h2>
          <p
            className="entree mx-auto mt-5 max-w-[30rem] text-[1.0625rem] leading-relaxed text-plume/70"
            style={vars({ "--d": "300ms" })}
          >
            {t("landing.maze.lead")}
          </p>
        </div>

        <div ref={steps}>
          <ol
            ref={rail}
            className="relative mx-auto mt-14 max-w-[64rem] lg:mt-20"
            style={vars({ "--t": still ? 1 : 0 })}
          >
            {/* The line, and the amber that fills it as it is read. */}
            <span
              aria-hidden
              className="absolute inset-y-5 start-5 w-0.5 rounded-full bg-plume/10 lg:start-1/2 lg:-ms-px"
            />
            <span
              aria-hidden
              className="absolute inset-y-5 start-5 w-0.5 origin-top rounded-full bg-[linear-gradient(to_bottom,#F1B860,#DB952C)] lg:start-1/2 lg:-ms-px"
              style={{ transform: "scaleY(var(--t))" }}
            />
            {GATES.map((gate, index) => {
              const mistake = MISTAKE_AT[gate];
              const left = index % 2 === 0;
              // A stop fills when the line reaches it.
              const reached = `clamp(0, calc((var(--t) - ${(index / GATES.length).toFixed(3)}) * 12), 1)`;
              return (
                <li
                  key={gate}
                  className="relative grid grid-cols-[2.5rem_1fr] items-start gap-x-5 py-5 lg:grid-cols-[1fr_4.5rem_1fr] lg:gap-x-0 lg:py-7"
                >
                  {/* The stop: an outlined ring, filled with navy once reached. */}
                  <span
                    aria-hidden
                    className="relative z-10 grid h-10 w-10 place-items-center lg:col-start-2 lg:row-start-1 lg:justify-self-center"
                  >
                    <span className="absolute inset-0 rounded-full bg-creme-100 ring-2 ring-plume/15" />
                    <span
                      className="absolute inset-0 rounded-full bg-plume shadow-[0_6px_16px_-6px_rgb(31_58_95/0.6)]"
                      style={{
                        opacity: reached,
                        transform: `scale(calc(0.7 + ${reached} * 0.3))`,
                      }}
                    />
                    <span
                      dir="ltr"
                      className="relative font-mono text-[0.75rem] font-semibold text-plume/60"
                    >
                      {number(index)}
                    </span>
                    <span
                      dir="ltr"
                      className="absolute font-mono text-[0.75rem] font-semibold text-creme-100"
                      style={{ opacity: reached }}
                    >
                      {number(index)}
                    </span>
                  </span>
                  {/* The step, on one side or the other of the line. */}
                  <div
                    className={cn(
                      "entree pt-1.5 lg:row-start-1 lg:pt-1",
                      left
                        ? "lg:col-start-1 lg:pe-10 lg:text-end"
                        : "lg:col-start-3 lg:ps-10",
                    )}
                    style={vars({ "--d": `${index * 60}ms` })}
                  >
                    <p className="text-[1.25rem] font-semibold leading-snug lg:text-[1.5rem]">
                      {t(`landing.maze.${gate}`)}
                    </p>
                    {mistake && (
                      <p
                        className={cn(
                          "mt-3 inline-flex max-w-[24rem] items-start gap-2.5 rounded-xl bg-[rgb(180_35_24/0.06)] px-3.5 py-2.5 text-start text-[0.9375rem] leading-snug text-revision ring-1 ring-[rgb(180_35_24/0.14)]",
                        )}
                      >
                        <TriangleAlert
                          aria-hidden
                          className="mt-0.5 h-4 w-4 shrink-0"
                          strokeWidth={1.75}
                        />
                        {t(`landing.costOfError.${mistake}Short`)}
                      </p>
                    )}
                  </div>
                </li>
              );
            })}
          </ol>
        </div>

        {/* What it all comes to, said once. */}
        <div
          ref={cost}
          className="mt-24 border-t border-plume/15 pt-12 lg:mt-32 lg:grid lg:grid-cols-12 lg:gap-x-14 lg:pt-16"
        >
          <p className={cn(H2, "max-w-[22ch] lg:col-span-8")}>
            <Mots text={t("landing.costOfError.title")} />
          </p>
          <p
            className="entree mt-8 max-w-[30ch] text-[1.125rem] leading-relaxed text-plume/75 lg:col-span-4 lg:mt-3"
            style={vars({ "--d": "400ms" })}
          >
            {t("landing.costOfError.close")}
          </p>
        </div>
      </div>
    </section>
  );
}
