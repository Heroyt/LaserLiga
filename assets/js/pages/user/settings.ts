import { registerPush, unregisterPush } from "../../push";
import { startLoading, stopLoading } from "../../loaders";
import { fetchPost } from "../../api/client";
import { sendPushTestNotification } from "../../api/endpoints/push";
import { createAvatar } from "@dicebear/core";
import {
    adventurer,
    adventurerNeutral,
    avataaars,
    avataaarsNeutral,
    bigEars,
    bigEarsNeutral,
    bigSmile,
    bottts,
    botttsNeutral,
    croodles,
    croodlesNeutral,
    dylan,
    funEmoji,
    glass,
    icons,
    identicon,
    initials,
    lorelei,
    loreleiNeutral,
    micah,
    miniavs,
    notionists,
    notionistsNeutral,
    openPeeps,
    personas,
    pixelArt,
    pixelArtNeutral,
    rings,
    shapes,
    thumbs
} from "@dicebear/collection";

export default function initUserSettings() {
    initAvatarSelect();

    if ("serviceWorker" in navigator && "PushManager" in window) {
        const section = document.getElementById("notification-settings") as HTMLDivElement;

        section.classList.remove("d-none");

        const registerBtn = document.getElementById("registerSubscription") as HTMLButtonElement;
        const unregisterBtn = document.getElementById("unregisterSubscription") as HTMLButtonElement;
        const testBtns = document.querySelectorAll<HTMLButtonElement>(".test-notification-btn");

        navigator.serviceWorker.getRegistration()
            .then(async registration => {
                if (!registration) {
                    throw new Error("No service worker registered");
                }
                await updateButtons(registration);

                registerBtn.addEventListener("click", async () => {
                    const result = await Notification.requestPermission();
                    await updateButtons(registration);
                    if (result === "denied") {
                        console.error("The user explicitly denied the permission request.");
                        return;
                    }
                    if (result === "granted") {
                        console.info("The user accepted the permission request.");
                    }
                    const subscribed = await registration.pushManager.getSubscription();
                    if (!subscribed) {
                        await registerPush(registration);
                    }
                });

                unregisterBtn.addEventListener("click", async () => {
                    const subscription = await registration.pushManager.getSubscription();
                    console.log(subscription);
                    if (subscription) {
                        await unregisterPush(subscription);
                    } else {
                        await registerPush(registration);
                    }
                    await updateButtons(registration);
                });
                for (const testBtn of testBtns) {
                    const type = testBtn.dataset.type ?? "test";
                    testBtn.addEventListener("click", () => {
                        startLoading();
                        sendPushTestNotification(type)
                            .then(() => {
                                stopLoading(true);
                            })
                            .catch(() => {
                                stopLoading(false);
                            });

                    });
                }
            });

        async function updateButtons(registration: ServiceWorkerRegistration) {
            const subscription = await registration.pushManager.getSubscription();
            console.log(subscription, Notification.permission);
            if (!subscription) {
                unregisterBtn.classList.add("d-none");
                for (const testBtn of testBtns) {
                    testBtn.classList.add("d-none");
                }
                registerBtn.classList.remove("d-none");
            } else {
                registerBtn.classList.add("d-none");
                unregisterBtn.classList.remove("d-none");
                for (const testBtn of testBtns) {
                    testBtn.classList.remove("d-none");
                }
            }
        }
    } else {
        let alertTxt = "Zařízení nepodporuje push notifikace.";
        if (navigator.platform === "iPhone") {
            alertTxt += "\nPush notifikace na iPhone vyžadují verzi iOS alespoň 16.4 a přidanou stránku na plochu.";
        }
        alert(alertTxt);
    }

    function initAvatarSelect() {
        const avatarTypes = document.querySelectorAll<HTMLDivElement>(".avatar-type");
        const avatarTypeInputs = document.querySelectorAll<HTMLInputElement>(".avatar-type input[type=radio]");
        const avatarSeed = document.getElementById("avatarSeed") as HTMLInputElement;
        const avatarSave = document.getElementById("avatarSave") as HTMLButtonElement;

        if (!avatarTypes || !avatarSeed || !avatarSave || !avatarTypeInputs) {
            return;
        }

        const typeCollection = {
            adventurer,
            adventurerNeutral,
            avataaars,
            avataaarsNeutral,
            bigEars,
            bigEarsNeutral,
            bigSmile,
            bottts,
            botttsNeutral,
            croodles,
            croodlesNeutral,
            dylan,
            funEmoji,
            glass,
            icons,
            identicon,
            initials,
            lorelei,
            loreleiNeutral,
            micah,
            miniavs,
            notionists,
            notionistsNeutral,
            openPeeps,
            personas,
            pixelArt,
            pixelArtNeutral,
            rings,
            shapes,
            thumbs
        };

        updateAvatarPreview();

        avatarSeed.addEventListener("input", () => {
            updateAvatarPreview();
        });

        avatarSave.addEventListener("click", () => {
            if (avatarSave.disabled) {
                return;
            }
            startLoading();
            fetchPost(avatarSave.dataset.action, { seed: avatarSeed.value, type: getSelectedAvatarType() })
                .then(() => {
                    stopLoading(true);
                })
                .catch(() => {
                    stopLoading(false);
                });
        });

        function getSelectedAvatarType() {
            for (const avatarTypeInput of avatarTypeInputs) {
                if (avatarTypeInput.checked) {
                    return avatarTypeInput.value;
                }
            }
            return null;
        }

        function updateAvatarPreview() {
            for (const avatarType of avatarTypes) {
                const image = avatarType.querySelector<HTMLImageElement>("img");
                const typeName = avatarType.dataset.type ?? "";
                const backgrounds = (avatarType.dataset.backgrounds ?? "")
                    .split(",")
                    .filter(bg => bg.length > 0);
                if (!typeName) {
                    console.error("Unknown type", avatarType);
                    continue;
                }

                if (!(typeName in typeCollection)) {
                    console.error(`Avatar type ${typeName} not found in collection.`);
                    continue;
                }

                const options: { seed: string, radius: number, backgroundColor?: string[] } = {
                    seed: avatarSeed.value,
                    radius: 50
                };

                if (backgrounds.length > 0) {
                    options.backgroundColor = backgrounds;
                }

                const avatar = createAvatar(
                    // @ts-ignore
                    typeCollection[typeName],
                    options
                );

                image.src = avatar.toDataUri();
            }
        }
    }
}