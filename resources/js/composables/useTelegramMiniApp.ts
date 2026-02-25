import {
    backButton,
    init,
    isTMA,
    mainButton,
    miniApp,
    postEvent,
    retrieveLaunchParams,
    retrieveRawInitData,
    themeParams,
    viewport,
    type User,
} from '@telegram-apps/sdk';
import { computed, ref, shallowRef } from 'vue';

interface AuthResponse {
    success: boolean;
    user?: {
        id: number;
        name: string;
        telegram_username?: string;
        telegram_photo_url?: string;
    };
    requires_phone_verification: boolean;
    requires_onboarding: boolean;
    redirect_url: string;
    error?: string;
}

const isInitialized = ref(false);
const isAuthenticating = ref(false);
const authError = ref<string | null>(null);
const launchParams = shallowRef<ReturnType<typeof retrieveLaunchParams> | null>(
    null,
);

/**
 * Composable for Telegram Mini App integration using @telegram-apps/sdk.
 *
 * @see https://docs.telegram-mini-apps.com/packages/telegram-apps-sdk
 */
export function useTelegramMiniApp() {
    /**
     * Whether the app is running inside a Telegram Mini App environment.
     */
    const isMiniApp = computed(() => {
        if (typeof window === 'undefined') return false;
        return isTMA();
    });

    /**
     * The raw init data string for backend authentication.
     * Use this for sending to the server for validation.
     */
    const initDataRaw = computed(() => {
        if (!isMiniApp.value) return '';
        try {
            return retrieveRawInitData() ?? '';
        } catch {
            return '';
        }
    });

    /**
     * The parsed user data from launch params.
     */
    const user = computed((): User | null => {
        if (!launchParams.value?.initData?.user) return null;
        return launchParams.value.initData.user;
    });

    /**
     * The color scheme from Telegram theme.
     */
    const colorScheme = computed(() => {
        if (!launchParams.value?.themeParams) return 'light';
        return launchParams.value.themeParams.bgColor ? 'dark' : 'light';
    });

    /**
     * Initialize the Telegram Mini App SDK.
     * Must be called before using other SDK features.
     */
    function initializeSdk() {
        if (isInitialized.value) return true;
        if (!isMiniApp.value) return false;

        try {
            // Initialize all SDK components
            init();

            // Retrieve and store launch params
            launchParams.value = retrieveLaunchParams();

            // Mount and configure components
            if (viewport.mount.isAvailable()) {
                viewport.mount();
                viewport.expand();
            }

            if (miniApp.mount.isAvailable()) {
                miniApp.mount();
                miniApp.ready();
            }

            if (themeParams.mount.isAvailable()) {
                themeParams.mount();
            }

            isInitialized.value = true;
            return true;
        } catch (error) {
            console.error('Failed to initialize Telegram Mini App SDK:', error);
            return false;
        }
    }

    /**
     * Authenticate the user using Mini App init data.
     * Sends the raw init data to the backend for validation and user creation/login.
     */
    async function authenticate(): Promise<AuthResponse | null> {
        const rawData = initDataRaw.value;

        if (!rawData) {
            authError.value = 'No init data available';
            return null;
        }

        isAuthenticating.value = true;
        authError.value = null;

        try {
            const response = await fetch('/auth/telegram/mini-app', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    Authorization: `tma ${rawData}`,
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Telegram-Mini-App': 'true',
                },
            });

            const data: AuthResponse = await response.json();

            if (!response.ok || data.error) {
                authError.value = data.error ?? 'Authentication failed';
                return null;
            }

            return data;
        } catch (error) {
            authError.value =
                error instanceof Error
                    ? error.message
                    : 'Authentication failed';
            return null;
        } finally {
            isAuthenticating.value = false;
        }
    }

    /**
     * Close the Mini App.
     */
    function close() {
        if (miniApp.close.isAvailable()) {
            miniApp.close();
        }
    }

    /**
     * Show the back button and set up click handler.
     */
    function showBackButton(onClick: () => void) {
        if (!backButton.mount.isAvailable()) return;

        backButton.mount();
        backButton.show();
        backButton.onClick(onClick);
    }

    /**
     * Hide the back button.
     */
    function hideBackButton() {
        if (backButton.hide.isAvailable()) {
            backButton.hide();
        }
    }

    /**
     * Configure and show the main button.
     */
    function showMainButton(text: string, onClick: () => void) {
        if (!mainButton.mount.isAvailable()) return;

        mainButton.mount();
        mainButton.setParams({ text, isVisible: true });
        mainButton.onClick(onClick);
    }

    /**
     * Hide the main button.
     */
    function hideMainButton() {
        if (mainButton.hide.isAvailable()) {
            mainButton.hide();
        }
    }

    /**
     * Trigger haptic feedback.
     */
    function hapticFeedback(
        type: 'success' | 'error' | 'warning' | 'light' | 'medium' | 'heavy',
    ) {
        try {
            if (type === 'success' || type === 'error' || type === 'warning') {
                postEvent('web_app_trigger_haptic_feedback', {
                    type: 'notification',
                    notification_type: type,
                });
            } else {
                postEvent('web_app_trigger_haptic_feedback', {
                    type: 'impact',
                    impact_style: type,
                });
            }
        } catch {
            // Haptic feedback not available
        }
    }

    return {
        isMiniApp,
        isInitialized,
        initDataRaw,
        launchParams,
        user,
        colorScheme,
        isAuthenticating,
        authError,
        initializeSdk,
        authenticate,
        close,
        showBackButton,
        hideBackButton,
        showMainButton,
        hideMainButton,
        hapticFeedback,
    };
}

const TMA_AUTH_KEY = 'tma_auth_completed';

/**
 * Initialize Mini App and auto-authenticate if needed.
 * Should be called once on app startup.
 */
export async function initializeTelegramMiniApp(
    isAuthenticated: boolean,
): Promise<AuthResponse | null> {
    if (typeof window === 'undefined') return null;

    const { isMiniApp, initializeSdk, authenticate } = useTelegramMiniApp();

    if (!isMiniApp.value) return null;

    // Initialize the SDK
    const initialized = initializeSdk();
    if (!initialized) return null;

    // If already authenticated via session, clear the flag and skip
    if (isAuthenticated) {
        sessionStorage.removeItem(TMA_AUTH_KEY);
        return null;
    }

    // Prevent re-authentication loops - if we already tried authenticating this session
    if (sessionStorage.getItem(TMA_AUTH_KEY)) {
        return null;
    }

    // Mark that we're attempting authentication
    sessionStorage.setItem(TMA_AUTH_KEY, 'pending');

    // Authenticate the user
    const result = await authenticate();

    if (result?.success) {
        sessionStorage.setItem(TMA_AUTH_KEY, 'success');
    } else {
        // Clear flag on failure so user can retry
        sessionStorage.removeItem(TMA_AUTH_KEY);
    }

    return result;
}
