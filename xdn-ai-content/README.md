# XDN AI Content Engine — Beta

Independent WordPress plugin for xedapninhbinh.com.

## Features
- Gemini Google Search grounding for SEO research
- Opportunity score, search intent, content gaps and sources
- GPT article generation
- Rank Math metadata integration when Rank Math is installed
- WooCommerce product suggestions/links
- Internal links to related posts
- Featured image selection from the WordPress Media Library
- Optional OpenAI image generation (requires an image-capable OpenAI model/API access)
- Draft creation and scheduled publishing
- Automatic research and publishing are OFF by default
- Stores API keys in WordPress options; never hard-coded

## Safety
The plugin does not scrape and republish arbitrary Google Images. Images from the web should only be used when the site/license permits reuse. AI-generated images are preferred when no reusable image is available.

## Install
1. Download the repository ZIP from the beta branch.
2. Extract it and zip only the `xdn-ai-content` directory as `xdn-ai-content.zip`.
3. WordPress → Plugins → Add New → Upload Plugin.
4. Activate and open **XDN AI Content**.
5. Add OpenAI and Gemini API keys.
6. Keep Auto Publish disabled until the workflow is tested.
