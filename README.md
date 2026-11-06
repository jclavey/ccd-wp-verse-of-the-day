# CCD Verse of the Day

A WordPress plugin that imports a year of daily Bible readings from a reading-plan spreadsheet and shows today's readings at the bottom of every page, with a link to the passages on BibleGateway.

It works with any theme and needs no theme code.

## Install

Copy this folder to `wp-content/plugins/ccd-verse-of-the-day/` (or upload it as a zip) and activate **CCD Verse of the Day**.

Requires WordPress 6.0+, PHP 7.4+ and the PHP `zip` extension.

## Import a reading plan

Go to **Bible Readings > Import** and upload the year's workbook, e.g. `CCD Bible Reading Plan 2026.xlsx`. The workbook needs:

- one sheet per month, named after the month (`January`, `February`, …);
- a header row in row 1;
- the day of the month in column B, and the Psalm, Old Testament and New Testament readings in columns C, D and E.

The year comes from the file name, or you can enter it on the form. Each day becomes one reading titled `Psalm 1; Genesis 1; Matthew 1`.

Importing happens in two steps. **Preview import** reads the file and lists every day as *Add*, *Update* (with the old readings shown struck through) or *Unchanged*, along with any rows it had to skip. Nothing is saved until you click **Import**. A preview expires after an hour.

Importing the same year again updates existing days rather than duplicating them, so you can fix a mistake in the spreadsheet and re-import it. You can also edit or add individual readings under **Bible Readings**.

Readings are only visible in the admin and in the footer bar. They have no public URLs, and they aren't exposed through the REST API, search or feeds, so future days stay hidden.

## Display

### Footer bar

By default, today's readings appear in a full-width bar on every page:

> 📖 Today's Bible Passages: **[Psalm 1; Genesis 1; Matthew 1](https://www.biblegateway.com/passage/?search=Psalm%201%3B%20Genesis%201%3B%20Matthew%201&version=NIVUK)**

With the Kadence theme the bar sits directly above the site footer. With other themes it appears at the end of the page. Turn it off with **Show on every page** under **Bible Readings > Settings**. The settings page also sets the label text and the BibleGateway translation (NIVUK by default).

### Shortcode

`[ccd_verse_of_the_day]` prints the same readings as inline text, with no bar or icon. It takes the font and colours of wherever you put it, so it works in a paragraph, a sidebar widget or a footer column, and you can use it with the bar on or off.

- `[ccd_verse_of_the_day]` shows: Today's Bible Passages: **Psalm 1; Genesis 1; Matthew 1**
- `[ccd_verse_of_the_day label="Read today:"]` uses a different label.
- `[ccd_verse_of_the_day label=""]` shows just the link.

### Daily Bible Readings card

For a featured spot such as the home page, insert the **Daily Bible Readings card** block pattern. In the block editor, open the inserter (+), go to **Patterns > Christ Church Dunstable** (or search "bible") and choose the card. It shows a "DAILY BIBLE READINGS" eyebrow, today's readings and a link to the reading plan, styled to match the home page's "Join us" card. The styling comes from the plugin, so the card looks the same on any page or theme.

In the editor, the card shows the shortcodes as text. The readings and link appear on the published page.

### Reading plan link

Set the card's link under **Bible Readings > Settings > Reading plan link**: a URL, such as a page or a PDF from the Media Library, and the link text. Update the URL there each year and every card follows. Leave it blank to hide the link. You can also use the link anywhere with the shortcode:

- `[ccd_reading_plan_link]` uses the link text from the settings.
- `[ccd_reading_plan_link text="Download the 2026 plan"]` uses different text.

The page shows the latest reading dated today or earlier, using the site's timezone. Readings don't depend on WP-Cron or scheduled posts, so the bar updates at midnight even on a quiet site. The result is cached until midnight and cleared whenever a reading changes.

### Styling

The bar takes its colours from the Kadence theme's global palette: palette 1 (maroon) for the background and palette 9 (white) for the text. It uses the theme's body font and content width. If you change the palette in the Customizer, the bar follows. With other themes it falls back to the Christ Church Dunstable colours. To set the colours directly, override these custom properties:

```css
.ccd-votd-bar { --ccd-votd-bar-background: #832427; --ccd-votd-bar-text: #fff; }
```

The shortcode text only adds bold to the link and otherwise inherits the theme's styles.

### Filters

- `ccd_votd_show_footer_bar` (bool): show or hide the footer bar on a given page.
- `ccd_votd_html` (string, array `$reading`): change the inline markup, which the shortcode and the bar both use.

## Licence

GPL-2.0-or-later.
