import { computed, ref } from 'vue';

/**
 * Telegram Mini App Web App interface.
 * @see https://core.telegram.org/bots/webapps#initializing-mini-apps
 */
interface TelegramWebApp {
    initData: string;
    initDataUnsafe: {
        query_id?: string;
        user?: TelegramUser;
        auth_date?: number;
        hash?: string;
    };
    version: string;
    platform: string;
    colorScheme: 'light' | 'dark';
    themeParams: Record<string, string>;
    isExpanded: boolean;
    viewportHeight: number;
    viewportStableHeight: number;
    headerColor: string;
    backgroundColor: string;
    isClosingConfirmationEnabled: boolean;
    ready: () => void;
    expand: () => void;
    close: () => void;
    enableClosingConfirmation: () => void;
    disableClosingConfirmation: () => void;
    setHeaderColor: (color: string) => void;
    setBackgroundColor: (color: string) => void;
    MainButton: TelegramMainButton;
    BackButton: TelegramBackButton;
    HapticFeedback: TelegramHapticFeedback;
}

interface TelegramUser {
    id: number;
    first_name: string;
    last_name?: string;
    username?: string;
    language_code?: string;
    is_premium?: boolean;
    photo_url?: string;
}

interface TelegramMainButton {
    text: string;
    color: string;
    textColor: string;
    isVisible: boolean;
    isActive: boolean;
    isProgressVisible: boolean;
    setText: (text: string) => void;
    onClick: (callback: () => void) => void;
    offClick: (callback: () => void) => void;
    show: () => void;
    hide: () => void;
    enable: () => void;
    disable: () => void;
    showProgress: (leaveActive?: boolean) => void;
    hideProgress: () => void;
}

interface TelegramBackButton {
    isVisible: boolean;
    onClick: (callback: () => void) => void;
    offClick: (callback: () => void) => void;
    show: () => void;
    hide: () => void;
}

interface TelegramHapticFeedback {
    impactOccurred: (style: 'light' | 'medium' | 'heavy' | 'rigid' | 'soft') => void;
    notificationOccurred: (type: 'error' | 'success' | 'warning') => void;
    selectionChanged: () => void;
}

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

declare global {
    interface Window {
        Telegram?: {
            WebApp: TelegramWebApp;
        };
    }
}

const isAuthenticating = ref(false);
const authError = ref<string | null>(null);

/**
 * Composable for Telegram Mini App integration.
 *
 * Provides utilities for detecting Mini App context, accessing the Telegram WebApp API,
 * and authenticating users via the Mini App init data.
 */
export function useTelegramMiniApp() {
    /**
     * Whether the app is running inside a Telegram Mini App.
     */
    const isMiniApp = computed(() => {
        if (typeof window === 'undefined') return false;
        return !!window.Telegram?.WebApp?.initData;
    });

    /**
     * The Telegram WebApp instance.
     */
    const webApp = computed(() => {
        if (typeof window === 'undefined') return null;
        return window.Telegram?.WebApp ?? null;
    });

    /**
     * The raw init data string for authentication.
     */
    const initData = computed(() => webApp.value?.initData ?? '');

    /**
     * The parsed user data from init data (unsafe, not validated).
     */
    const user = computed(() => webApp.value?.initDataUnsafe?.user ?? null);

    /**
     * The color scheme from Telegram (light or dark).
     */
    const colorScheme = computed(() => webApp.value?.colorScheme ?? 'light');

    /**
     * Authenticate the user using Mini App init data.
     * Sends the init data to the backend for validation and user creation/login.
     */
    async function authenticate(): Promise<AuthResponse | null> {
        if (!initData.value) {
            authError.value = 'No init data available';
            return null;
        }

        isAuthenticating.value = true;
        authError.value = null;

        try {
            const response = await fetch('/auth/telegram/mini-app', {
                method: 'POST',
                headers: {
                    'Authorization': `tma ${initData.value}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const data: AuthResponse = await response.json();

            if (!response.ok || data.error) {
                authError.value = data.error ?? 'Authentication failed';
                return null;
            }

            return data;
        } catch (error) {
            authError.value = error instanceof Error ? error.message : 'Authentication failed';
            return null;
        } finally {
            isAuthenticating.value = false;
        }
    }

    /**
     * Initialize the Mini App (call ready() and expand()).
     */
    function initialize() {
        if (!webApp.value) return;

        webApp.value.ready();
        webApp.value.expand();
    }

    /**
     * Close the Mini App.
     */
    function close() {
        webApp.value?.close();
    }

    /**
     * Trigger haptic feedback.
     */
    function hapticFeedback(type: 'success' | 'error' | 'warning' | 'light' | 'medium' | 'heavy') {
        if (!webApp.value?.HapticFeedback) return;

        if (type === 'success' || type === 'error' || type === 'warning') {
            webApp.value.HapticFeedback.notificationOccurred(type);
        } else {
            webApp.value.HapticFeedback.impactOccurred(type);
        }
    }

    return {
        isMiniApp,
        webApp,
        initData,
        user,
        colorScheme,
        isAuthenticating,
        authError,
        authenticate,
        initialize,
        close,
        hapticFeedback,
    };
}

/**
 * Initialize Mini App and auto-authenticate if needed.
 * Should be called once on app startup.
 */
export async function initializeTelegramMiniApp(isAuthenticated: boolean): Promise<AuthResponse | null> {
    if (typeof window === 'undefined') return null;

    const { isMiniApp, initialize, authenticate } = useTelegramMiniApp();

    if (!isMiniApp.value) return null;

    // Initialize the Mini App
    initialize();

    // If already authenticated, no need to re-authenticate
    if (isAuthenticated) return null;

    // Authenticate the user
    return authenticate();
}
