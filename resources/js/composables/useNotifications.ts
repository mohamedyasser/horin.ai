import { ref, onMounted, onUnmounted, computed } from 'vue';
import type { AlertNotification } from '@/types/alerts';

export function useNotifications(userId: string) {
    const notifications = ref<AlertNotification[]>([]);
    const toastQueue = ref<AlertNotification[]>([]);
    const isConnected = ref(false);
    const reconnectAttempts = ref(0);
    const MAX_RECONNECT_DELAY = 30000; // 30 seconds

    let reconnectTimeout: ReturnType<typeof setTimeout> | null = null;

    const unreadCount = computed(() =>
        notifications.value.filter(n => !n.read_at).length
    );

    const setupAlertChannel = () => {
        if (!window.Echo) {
            console.error('Echo not initialized');
            return;
        }

        window.Echo
            .private(`user.${userId}.alerts`)
            .listen('.alert.triggered', (event: AlertNotification) => {
                handleAlert(event);
            })
            .error((error: Error) => {
                console.error('WebSocket error:', error);
                isConnected.value = false;
                scheduleReconnect();
            });

        // Mark as connected when subscription succeeds
        isConnected.value = true;
        reconnectAttempts.value = 0;
    };

    const handleAlert = (event: AlertNotification) => {
        // Add to notifications list
        notifications.value.unshift(event);

        // Show toast for high priority
        if (['critical', 'high'].includes(event.priority)) {
            toastQueue.value.push(event);
        }

        // Play sound for critical alerts
        if (event.priority === 'critical') {
            playSound();
        }
    };

    const scheduleReconnect = () => {
        if (reconnectTimeout) return;

        reconnectAttempts.value++;
        const delay = Math.min(
            Math.pow(2, reconnectAttempts.value) * 1000,
            MAX_RECONNECT_DELAY
        );

        console.log(`Reconnecting in ${delay}ms (attempt ${reconnectAttempts.value})`);

        reconnectTimeout = setTimeout(() => {
            reconnectTimeout = null;
            window.Echo?.connector.connect();
            setupAlertChannel();
        }, delay);
    };

    const fetchNotifications = async () => {
        try {
            const response = await fetch('/api/notifications?per_page=50');
            const data = await response.json();
            notifications.value = data.data;
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    };

    const fetchMissedNotifications = async () => {
        const lastSeen = notifications.value[0]?.created_at;
        if (!lastSeen) return;

        try {
            const response = await fetch(`/api/notifications?since=${lastSeen}`);
            const data = await response.json();

            if (data.data.length > 0) {
                notifications.value = [...data.data, ...notifications.value];
            }
        } catch (error) {
            console.error('Failed to fetch missed notifications:', error);
        }
    };

    const markAsRead = async (notificationId: string) => {
        try {
            await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const notification = notifications.value.find(n => n.id === notificationId);
            if (notification) {
                notification.read_at = new Date().toISOString();
            }
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            notifications.value.forEach(n => {
                if (!n.read_at) {
                    n.read_at = new Date().toISOString();
                }
            });
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };

    const dismissToast = (notificationId: string) => {
        const index = toastQueue.value.findIndex(n => n.id === notificationId);
        if (index !== -1) {
            toastQueue.value.splice(index, 1);
        }
    };

    const playSound = () => {
        const audio = new Audio('/sounds/alert-critical.mp3');
        audio.play().catch(() => {
            // Audio play failed (likely due to autoplay policy)
        });
    };

    // Handle tab visibility change
    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            fetchMissedNotifications();

            if (!isConnected.value) {
                window.Echo?.connector.connect();
                setupAlertChannel();
            }
        }
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        fetchNotifications();
        setupAlertChannel();
    });

    onUnmounted(() => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
        }
    });

    return {
        notifications,
        toastQueue,
        unreadCount,
        isConnected,
        markAsRead,
        markAllAsRead,
        dismissToast,
        fetchNotifications,
    };
}
