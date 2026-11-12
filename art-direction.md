Kind of business: local directory and job board (local services tier). Real photos, flat color, strong type, light hover layer. No shaders, particles, 3D, glows or dark tech heroes.

# [RBH] Art direction: Key tag

Picked by Will, 2026-11-12. Idea: `C:\Vaults\TechDad-Vault\02-Brands\Recovery-Business-Hub\rbh-v2-idea.md`. Research: `...\research.md` in the same folder. Every page in the v2 build cites this file and uses only what's in it.

## The metaphor

The recovery key tag. The plastic tag on a keyring you get for clean time. Rounded at one end, a punched hole, a ring through it, stamped lettering, a color that means something. People from the rooms recognize it on sight. Everyone else sees a clean tag. That's the point.

No NA or AA logos, names, slogans or tag wording anywhere. The shape and the colors only. RBH is not affiliated with any fellowship and never looks like it is.

## Type

- **Display:** Bricolage Grotesque, 800 (headlines), 500 (subheads). Lowercase wordmark: `recovery business hub`.
- **Workhorse:** Public Sans, 400 and 600. Body, forms, cards, buttons.
- Self-host both as woff2 in `fonts/`. Montserrat and Open Sans come out (both on the ban list).
- Headlines in sentence case. Tight leading (1.05). No italics, no all caps.

## Palette

| Role | Name | Hex | Use |
|---|---|---|---|
| Dominant | Concrete | `#ECECE8` | Page background |
| Dominant | Ink | `#141414` | Text, borders, black tags |
| Dominant | Tag white | `#FFFFFF` | Card faces, form fields |
| Support | Tag green | `#1E8A4C` fill, `#17703D` text | "Said yes" policy, success |
| Accent | Tag orange | `#F26B1D` | The one action per screen (Find work, Post a job, Apply). Ink text on it, never white (white fails contrast) |

Tag stub colors carry meaning. They never get explained on the site, they just stay consistent:
- Green stub: job, employer policy "no background check".
- Blue stub `#2563A8`: job, policy "case by case".
- Ink stub: a recovery-owned business listing.

Muted text: `#4A4A47` on concrete. Borders: `#D6D6D0`. Error: `#B91C1C`.

## Signature motif: the key tag

Source: `assets/keytag.svg`. A horizontal tag: colored stub on the left with a punched hole, white face on the right. Same 2px stroke and 6px corner on every icon and card.

It shows up:
1. Every job card and every business card is a tag (stub + face).
2. Hero: a short stack of three tags fanned on a ring (one job, one business, one blank "yours here").
3. Favicon and small logo mark: a single tag. (Full logo: keep or redo is Will's call, still open.)
4. Empty states and the 404: a lone blank tag.
5. Section dividers: a thin ink line ending in a ring.

## Photos

- No Shutterstock connected (checked 2026-11-12). No stock in the hero. The hero is type plus the tag stack.
- Real photos come from listed owners, with their OK: their crew, their shop, their work. Shown on their own profile only.
- Site-level photos (About, Post a job) only from real Cincinnati sources Will provides. One treatment for all of them: tint-and-crush. Grayscale, autocontrast 2%, blend with Ink at 25%, then a Concrete lift on the highlights. Owner logos are never graded.
- Never a fake job photo, never a fake screenshot, never people from the rooms.

## Texture

`assets/grain.png`: tileable 128px noise, 3% opacity overlay on the concrete background only. Never on cards.

## Layout rules

- Equal left and right content edges in every section. Max content width 1120px, 16px gutter on phones.
- Phone first. Most job seekers will be on a phone. The apply form fits one screen at 390 wide.
- No captions under photos. Credits in one footer line.
- No carousels, no auto-rotating anything, no tilt cards.
- Two doors on the home page: Find work, Find a business. One orange button per screen.

## Hover layer

- Tag cards lift 2px, border goes from `#D6D6D0` to Ink.
- Photos zoom 1.02 inside their frame.
- Buttons shift 1px up on hover, press in on click.
- Link underlines draw in left to right.
- 150 to 250ms, ease-out. All of it off under `prefers-reduced-motion`. Nothing delays a form or a phone number.

## Ban list

No navy (the old `#0C1B33` look is gone), no navy-plus-teal, no glows, no side-tab borders on cards, no plain rounded-card grids as the only layout, no emoji icons, no cream backgrounds, no italic accent words, no numbered 01/02/03 labels, no monospace labels, no pill buttons (6px corners). No recovery fellowship logos or slogans. If a page could be dropped into any other site and look fine, it's wrong. Redo it.
