<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import {
    MessageSquare,
    Send,
    Trash2,
    Loader2,
    LogIn,
} from 'lucide-vue-next';
import LocalizedLink from '@/components/LocalizedLink.vue';
import type { NewsComment } from '@/types/news';

interface Props {
    newsId: string;
    initialCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    initialCount: 0,
});

const { t, locale } = useI18n();
const page = usePage();

// State
const comments = ref<NewsComment[]>([]);
const newComment = ref('');
const isLoading = ref(false);
const isSubmitting = ref(false);
const deletingId = ref<string | null>(null);
const hasLoaded = ref(false);
const commentsCount = ref(props.initialCount);

// Computed
const isAuthenticated = computed(() => !!page.props.auth?.user);
const currentUserId = computed(() => page.props.auth?.user?.id);

// Load comments
const loadComments = async () => {
    if (hasLoaded.value) return;

    isLoading.value = true;
    try {
        const response = await fetch(`/api/news/${props.newsId}/comments`);
        if (response.ok) {
            const data = await response.json();
            comments.value = data.data;
            hasLoaded.value = true;
        }
    } catch (error) {
        console.error('Failed to load comments:', error);
    } finally {
        isLoading.value = false;
    }
};

// Submit comment
const submitComment = async () => {
    if (!newComment.value.trim() || isSubmitting.value) return;

    isSubmitting.value = true;
    try {
        const response = await fetch(`/api/news/${props.newsId}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify({ content: newComment.value }),
        });

        if (response.ok) {
            const data = await response.json();
            comments.value.unshift(data.comment);
            commentsCount.value = data.comments_count;
            newComment.value = '';
        }
    } catch (error) {
        console.error('Failed to post comment:', error);
    } finally {
        isSubmitting.value = false;
    }
};

// Delete comment
const deleteComment = async (commentId: string) => {
    if (deletingId.value) return;

    deletingId.value = commentId;
    try {
        const response = await fetch(`/api/news/${props.newsId}/comments/${commentId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        if (response.ok) {
            const data = await response.json();
            comments.value = comments.value.filter(c => c.id !== commentId);
            commentsCount.value = data.comments_count;
        }
    } catch (error) {
        console.error('Failed to delete comment:', error);
    } finally {
        deletingId.value = null;
    }
};

// Get CSRF token from cookies
const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

// Format date
const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Get initials for avatar
const getInitials = (name: string) => {
    return name
        .split(' ')
        .map(n => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

// Load comments on mount
loadComments();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <MessageSquare class="size-5" />
                {{ t('news.comments.title') }}
                <span v-if="commentsCount" class="text-sm font-normal text-muted-foreground">
                    ({{ commentsCount }})
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Comment Form -->
            <div v-if="isAuthenticated" class="mb-6">
                <Textarea
                    v-model="newComment"
                    :placeholder="t('news.comments.placeholder')"
                    class="mb-3 min-h-[100px]"
                    :disabled="isSubmitting"
                />
                <Button
                    :disabled="!newComment.trim() || isSubmitting"
                    @click="submitComment"
                >
                    <Loader2 v-if="isSubmitting" class="me-2 size-4 animate-spin" />
                    <Send v-else class="me-2 size-4" />
                    {{ t('news.comments.submit') }}
                </Button>
            </div>

            <!-- Login prompt -->
            <div v-else class="mb-6 rounded-lg border border-dashed border-border p-4 text-center">
                <p class="mb-3 text-sm text-muted-foreground">
                    {{ t('news.comments.loginRequired') }}
                </p>
                <Button as-child variant="outline" size="sm">
                    <LocalizedLink href="/login">
                        <LogIn class="me-2 size-4" />
                        {{ t('common.login') }}
                    </LocalizedLink>
                </Button>
            </div>

            <Separator v-if="comments.length || isLoading" class="mb-6" />

            <!-- Loading state -->
            <div v-if="isLoading" class="space-y-4">
                <div v-for="i in 3" :key="i" class="flex gap-3">
                    <Skeleton class="size-10 shrink-0 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-4 w-1/4" />
                        <Skeleton class="h-4 w-full" />
                        <Skeleton class="h-4 w-3/4" />
                    </div>
                </div>
            </div>

            <!-- Comments list -->
            <div v-else-if="comments.length" class="space-y-4">
                <div
                    v-for="comment in comments"
                    :key="comment.id"
                    class="flex gap-3"
                >
                    <Avatar class="size-10 shrink-0">
                        <AvatarFallback>
                            {{ getInitials(comment.user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="font-medium">{{ comment.user.name }}</span>
                                <span class="ms-2 text-xs text-muted-foreground">
                                    {{ formatDate(comment.created_at) }}
                                </span>
                            </div>
                            <Button
                                v-if="currentUserId === comment.user.id"
                                variant="ghost"
                                size="icon"
                                class="size-8 text-muted-foreground hover:text-destructive"
                                :disabled="deletingId === comment.id"
                                @click="deleteComment(comment.id)"
                            >
                                <Loader2 v-if="deletingId === comment.id" class="size-4 animate-spin" />
                                <Trash2 v-else class="size-4" />
                            </Button>
                        </div>
                        <p class="mt-1 text-sm whitespace-pre-wrap">{{ comment.content }}</p>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else class="py-8 text-center">
                <MessageSquare class="mx-auto size-12 text-muted-foreground/30" />
                <h3 class="mt-4 text-sm font-medium">{{ t('news.comments.empty') }}</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('news.comments.emptyDescription') }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
