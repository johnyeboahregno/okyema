You are working in the existing Okyema application repository. Add a second, radically simple assistant interface based on the supplied `Okyema_Brand_Page.html`. I want to choose between the existing Okyema interface and this new interface using an environment variable.

## First, inspect the application

Read the repository instructions and trace the existing application’s routing, authentication, settings, AI provider integration, Notion integration, conversation handling, design assets, tests and deployment configuration. Identify the current default screen and how the app loads environment variables.

Do not start by creating a separate app or replacing the existing interface. This is a second presentation mode of the same Okyema application.

## Interface mode

Add a clearly named environment variable:

`OKYEMA_UI_MODE=classic|simple`

- `classic` renders the existing Okyema interface.
- `simple` renders the new assistant interface.
- If the variable is absent or invalid, default to `classic` and report an invalid value through the application’s normal configuration logging.
- Document where the value is set for local development and production.
- Make the mode selection at the application shell or route boundary appropriate to the existing architecture. Avoid duplicating domain services or API routes.
- Explain whether this application reads environment variables at build time or runtime. If it is build time, state clearly that changing the value requires a rebuild and redeployment. Do not imply that editing the variable will change an already-running client without that step.

The environment variable selects the interface for the deployment. It is not a user-facing toggle, an account preference or a query parameter.

## New `simple` mode

The new screen should feel cool, modern and eye-catching, but remain exceptionally simple. Follow the supplied brand page as the visual reference and use the **latest approved Okyema green assets**. The original `logoGreen.png` mark is canonical: do not redraw, recolour, simplify or replace it. Use the approved light and dark logo variants appropriately. Do not bring back the older cyan, blue and violet Command Frame branding.

The primary screen has:

1. Okyema identity in a restrained header.
2. A visible active context: Regno, Launchpad, Personal or All contexts when authorised.
3. A large, clear text input for a natural-language request.
4. A prominent microphone control immediately below it.
5. Conversation results beneath or above the input, depending on screen size.
6. A gear icon that opens Settings.

Do not add dashboards, sidebars, task grids or extra primary navigation to `simple` mode. A few optional example prompts are acceptable on an empty screen, but they must disappear or recede when the conversation starts.

Make the layout excellent on a narrow phone and equally intentional on desktop. Support automatic light and dark themes, keyboard navigation, screen readers, reduced motion, recording states, loading states and useful errors.

## Behaviour

The two interface modes must use the **same** authentication, user account, Notion connection, AI provider credentials, context boundaries, conversation history, approval rules, audit events and backend services.

Typed and spoken requests enter the same existing assistant pipeline. For voice:

- Start and stop recording with a clear control.
- Transcribe the audio using the configured speech service.
- Display the transcript for review and editing before submission by default.
- Handle denied microphone permission, silence, failed transcription and unsupported browsers.
- Do not claim that speech works merely because the microphone button renders.

Show source links when an answer uses Notion records. For changes, show the existing approval preview and require confirmation before executing. `simple` mode must never weaken permissions, context isolation or approval requirements to achieve a cleaner appearance.

Settings must use the existing Okyema settings and secure credential handling. In `simple` mode, the gear can show those settings in a modal, drawer or dedicated page, whichever fits the existing architecture and accessibility conventions. It must allow configuration of the Notion connection, OpenAI, Claude and DeepSeek API credentials, default provider/model, and speech provider wherever those settings are actually supported. Never display saved secret values.

## How to use the supplied HTML

Treat `Okyema_Brand_Page.html` as a **design reference**, not production application code. Recreate its appearance using the existing framework, components and styling conventions. Do not paste its preview-only JavaScript into production. Replace its simulated responses and placeholder settings with the real Okyema behaviours.

Use the latest Okyema green brand assets already present in the workspace or supplied with this task. If they cannot be found, stop and identify the missing assets instead of inventing a replacement logo.

## Implementation boundaries

- Preserve `classic` visually and functionally.
- Keep shared business logic in the existing services; implement only mode-specific layout and presentation components where possible.
- Ensure direct links and refreshes still work in both modes.
- Do not create two divergent settings systems or conversation stores.
- Do not alter the existing production mode until `simple` passes its checks.
- Add the environment variable to the configuration schema and `.env.example`.
- Update deployment instructions with examples for both values.

## Acceptance checks

Demonstrate both modes from the same codebase:

1. With `OKYEMA_UI_MODE=classic`, the existing screen and its main journeys still work.
2. With `OKYEMA_UI_MODE=simple`, the new green Okyema interface renders on phone and desktop.
3. A typed request reaches the real assistant and returns a result.
4. A spoken request produces an editable transcript and then follows the same path.
5. A Notion-backed answer includes a working source link.
6. A proposed Notion change shows a preview, can be cancelled, and executes only after approval.
7. The gear opens the real settings without exposing API keys.
8. Context isolation works identically in both modes.
9. Invalid or missing mode configuration resolves to `classic`.
10. Lint, typecheck, relevant tests and production build pass.

Start by reporting what you found in the repository and where the mode switch belongs. Then implement the smallest complete change, run the checks and summarise the files changed, evidence from both modes and any genuine limitations.