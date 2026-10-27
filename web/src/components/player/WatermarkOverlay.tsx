/**
 * Anti-piracy deterrent: a semi-transparent watermark (the viewer's e-mail)
 * that slowly drifts between the corners of the video container so it cannot
 * be cropped or masked out of a screen recording.
 *
 * Purely visual — pointer-events are disabled so the iframe stays clickable.
 */
export function WatermarkOverlay({ text }: { text: string }) {
  return (
    <div aria-hidden className="pointer-events-none absolute inset-0 z-10 select-none overflow-hidden">
      <style>{`
        /* Drifts on transform rather than top/left: this runs for the whole
           length of every lesson, and layout must not be recalculated on each
           frame of a playing video. The animated box is overlay-sized, so a
           percentage translate resolves against the overlay — moving the label
           from (5%, 6%) to (55%, 84%), the same four corners as before. */
        @keyframes ebni-watermark-drift {
          0%, 21%   { transform: translate(0, 0); }
          25%, 46%  { transform: translate(50%, 0); }
          50%, 71%  { transform: translate(50%, 78%); }
          75%, 96%  { transform: translate(0, 78%); }
          100%      { transform: translate(0, 0); }
        }
      `}</style>
      <div
        className="absolute inset-0 will-change-transform"
        style={{ animation: 'ebni-watermark-drift 40s linear infinite' }}
      >
        <span
          dir="ltr"
          className="absolute left-[5%] top-[6%] max-w-[60%] truncate rounded bg-black/20 px-2 py-0.5 text-[11px] font-medium text-white opacity-35"
        >
          {text}
        </span>
      </div>
    </div>
  )
}
