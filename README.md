[![Codacy Badge](https://app.codacy.com/project/badge/Grade/da02e51a5d774c50abb9ff1978aa4271)](https://app.codacy.com/gh/FacuM/stickersimporter/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)

<h1 style="text-align: center;"> StickersImporter </h1>

A simple Telegram bot written in PHP to quickly upload one or more images into a personal sticker pack, avoiding the size and format limitations of Telegram's official implementation.

## System requirements
- Any **x86** or **arm64** CPU running at any speed
- **1 GB** of memory running at **any speed** during image build time, **4 MB** of free memory at runtime
- **2 GB** of **storage space**
- Any kind of internet connection

## Getting started
- [Install docker](https://docs.docker.com/desktop/install/linux-install/) as explained in the linked guide.
- [Give yourself permission to run Docker commands](https://docs.docker.com/engine/install/linux-postinstall/) by following the guide linked here.
- Clone this repository wherever you want, just make sure you'd have write permission with the user you're currently logged in.

    `git clone https://github.com/FacuM/stickersimporter`
- Change to the created directory by running `cd stickersimporter`.
- Now, using your favorite text editor, create a new file called `.env` and copy the contents of `.env.example` into it. Within that file, set your bot's **API key** (as provided by **@BotFather**) and its **username**.
- Now, run `docker compose up --build` to get going. The first run might take a few minutes, so you'll probably want to find something else to do in the meantime.
- When the bot is ready to listen, a message like this will appear:

    > backend-1  | 2024-05-04 19:22:38 - PING!

## License

StickersImporter is open-sourced software licensed under the [MIT license](LICENSE).
