---
subject: "Changelog v1.0.5 — LinkedIn PDFs, video, team roles, Discord posting & more"
---

# Changelog v1.0.5 — LinkedIn PDFs, video, team roles, Discord posting & more

By TryPost Product Team • [Release v1.0.5](https://github.com/trypostit/trypost/releases/tag/v1.0.5)

Hey, it's been a few weeks, so this is a bigger catch-up than usual. Here's everything that's shipped in TryPost.

## LinkedIn PDF carousels

You can post swipeable PDF carousels to LinkedIn now, the format people actually stop to read. Attach a PDF and TryPost sets the post up as a document for you, with nothing extra to configure. You can also preview PDFs right in the media gallery, so you don't have to open them in a new tab to check you grabbed the right file.

## Better video publishing

Bluesky handles video now: upload a clip and it embeds natively in your post. We also made video uploads steadier everywhere. Big videos to X and LinkedIn were failing with memory errors, and that's fixed. Pinterest video pins get a cover image on their own, and YouTube Shorts can't be scheduled without the text that becomes their title, so they stop failing at publish time.

## Team roles and client review

You can invite people into a workspace with the right role: Admin, Member, or Viewer. Viewers are read-only, but they can open any post and leave comments. This is the one I'm most excited about: give a client or a manager a Viewer seat, let them review what's scheduled, and collect their feedback right on the post, with no chance of them changing something by accident. Members run the day-to-day, while connecting accounts and managing the team stays with Admins and the owner.

## Post straight to Discord, smarter automations

Discord is a channel now. Connect a server, pick the exact channel, add mentions, and check a live preview before it goes out, and Discord engagement shows up in your metrics like everything else. Automations got smarter too: they read both RSS and Atom feeds now, and you can pull variables straight from a feed, so each item fills in its own post instead of you copying them by hand.

## AI content templates

Generating a post with AI starts with picking a format now: a single image card, a carousel, or a tweet-style card (one even comes with a verified badge, and one sits on a photo background). You pick the visual style on the same screen, and these templates work inside automations too, so your scheduled content can use them.

## New features

- Post swipeable PDF carousels to LinkedIn, and preview PDFs without leaving the editor
- Upload and embed videos on Bluesky
- Invite clients or stakeholders as Viewers, who can review and comment but not edit
- Publish straight to Discord channels and groups
- AI post formats: image cards, carousels, and tweet-style cards
- RSS and Atom automations that pull variables from the feed

## Fixes

- Big video uploads to X and LinkedIn no longer run out of memory
- Pinterest video pins publish with a cover image automatically
- YouTube Shorts can't be scheduled without their title text anymore
- An image and a video can't be combined in the same post anymore
- Per-platform settings (aspect ratio, TikTok privacy, Pinterest boards, Discord channel) carry through the API and MCP now, not just the app
- Telegram connection problems show a clear message instead of failing quietly

Questions or ideas? Join the conversation in [GitHub Discussions](https://github.com/orgs/trypostit/discussions).

Cheers,
Paulo from TryPost.it
