function mountSpotifyPlayer() {
	const slot = document.querySelector("[data-funky-spotify-embed]");
	if (!slot) return;

	const localizedUrl = window.FunkyCommerceThemeSettings?.spotify?.embedUrl || "";
	const title = window.FunkyCommerceThemeSettings?.spotify?.title || "";
	const description = window.FunkyCommerceThemeSettings?.spotify?.description || "";
	const candidate = slot.dataset.funkySpotifyEmbed || localizedUrl;
	let embedUrl;
	try {
		embedUrl = new URL(candidate);
	} catch {
		return;
	}
	if (
		embedUrl.protocol !== "https:" ||
		embedUrl.hostname !== "open.spotify.com" ||
		!/^\/embed\/(?:track|album|playlist|artist|show|episode)\/[A-Za-z0-9]{10,64}$/.test(embedUrl.pathname)
	) {
		return;
	}
	if (slot.querySelector("iframe")) return;

	const iframe = document.createElement("iframe");
	iframe.title = title || "Spotify player";
	iframe.src = embedUrl.href;
	iframe.loading = "lazy";
	iframe.allow = "autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture";
	iframe.referrerPolicy = "strict-origin-when-cross-origin";
	iframe.style.border = "0";
	iframe.style.borderRadius = "12px";
	iframe.style.height = "352px";
	iframe.style.width = "100%";
	const content = [];
	if (title) {
		const heading = document.createElement("h2");
		heading.textContent = title;
		content.push(heading);
	}
	if (description) {
		const copy = document.createElement("p");
		copy.textContent = description;
		content.push(copy);
	}
	content.push(iframe);
	slot.replaceChildren(...content);
	slot.hidden = false;
}

mountSpotifyPlayer();

function mountVideoHeroControls() {
	document.querySelectorAll(".funkycommerce-native-video-hero").forEach((hero) => {
		const media = hero.querySelector(".funkycommerce-native-video-hero-media");
		const playbackControl = hero.querySelector(".funkycommerce-native-video-hero-playback");
		const muteControl = hero.querySelector(".funkycommerce-native-video-hero-mute");
		if (!media || !playbackControl || !muteControl) return;

		const applyMuted = () => {
			const muted = hero.dataset.videoMuted === "true";
			if (media instanceof HTMLVideoElement) {
				media.muted = muted;
				return;
			}
			if (!(media instanceof HTMLIFrameElement) || !media.contentWindow) return;
			const message = hero.dataset.videoProvider === "youtube"
				? { event: "command", func: muted ? "mute" : "unMute", args: [] }
				: { method: "setVolume", value: muted ? 0 : 1 };
			media.contentWindow.postMessage(JSON.stringify(message), "*");
			if (!muted && hero.dataset.videoProvider === "youtube") {
				media.contentWindow.postMessage(
					JSON.stringify({ event: "command", func: "playVideo", args: [] }),
					"*",
				);
			}
		};

		if (media instanceof HTMLIFrameElement) {
			media.addEventListener("load", applyMuted);
		}
		applyMuted();

		playbackControl.addEventListener("click", () => {
			const playing = hero.dataset.videoPlaying === "true";
			if (media instanceof HTMLVideoElement) {
				if (playing) media.pause();
				else void media.play();
			} else if (media instanceof HTMLIFrameElement) {
				if (playing) {
					media.dataset.videoSrc = media.src;
					media.removeAttribute("src");
				} else if (media.dataset.videoSrc) {
					media.src = media.dataset.videoSrc;
				}
			}
			const next = !playing;
			hero.dataset.videoPlaying = String(next);
			playbackControl.textContent = next ? "\u275A\u275A" : "\u25B6";
			playbackControl.setAttribute("aria-label", next ? "Pause background video" : "Play background video");
		});

		muteControl.addEventListener("click", () => {
			const next = hero.dataset.videoMuted !== "true";
			hero.dataset.videoMuted = String(next);
			applyMuted();
			muteControl.textContent = next ? "\uD83D\uDD07" : "\uD83D\uDD0A";
			muteControl.setAttribute("aria-label", next ? "Unmute background video" : "Mute background video");
		});
	});
}

mountVideoHeroControls();
