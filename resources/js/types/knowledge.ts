/**
 * عقود قاعدة المعرفة — وثيقة 06 §15.
 */

import type { Tone } from '@/types';
import type { AssistantLevel, EnumRef } from '@/types/conversations';

export type KnowledgeStatus = 'draft' | 'review' | 'published' | 'archived';
export type KnowledgeKind = 'article' | 'faq' | 'procedure' | 'policy';
export type SourceKind = 'link' | 'file' | 'note';
export type FeedbackKind = 'feedback' | 'unanswered' | 'suggestion';

/** ما تشترك فيه بطاقة القسم في القائمة وصفحة تفاصيله. */
export interface SectionKnowledge {
    items: number;
    published: number;
    screens: number;
    sources: number;
    open_notes: number;
    /** 0–100 — تغطية لا كمية. */
    completion: number;
    met: Record<string, boolean>;
    status: string;
    status_label: string;
    tone: Tone;
}

export interface SectionKnowledgeRow extends SectionKnowledge {
    id: number;
    name: string;
    description: string | null;
    ai_enabled: boolean;
    updated_at: string;
}

export interface SectionDetail extends SectionKnowledge {
    id: number;
    name: string;
    description: string | null;
    ai_enabled: boolean;
    knowledge_enabled: boolean;
    level: EnumRef<AssistantLevel> | null;
}

export interface KnowledgeItemRow {
    id: number;
    title: string;
    summary: string | null;
    body: string;
    kind: EnumRef<KnowledgeKind>;
    status: EnumRef<KnowledgeStatus>;
    version: number;
    tags: string[];
    editor: string | null;
    updated_at: string;
}

export interface ScreenRow {
    id: number;
    name: string;
    path: string | null;
    description: string | null;
    image_path: string | null;
    elements: string[];
    actions: string[];
    states: string[];
}

export interface SourceRow {
    id: number;
    kind: EnumRef<SourceKind>;
    label: string;
    url: string | null;
    note: string | null;
    created_at: string;
}

export interface FeedbackRow {
    id: number;
    kind: EnumRef<FeedbackKind>;
    body: string;
    occurrences: number;
    resolved: boolean;
    created_at: string;
}

export interface VersionRow {
    id: number;
    version: number;
    title: string;
    summary: string | null;
    body: string;
    status: EnumRef<KnowledgeStatus>;
    author: string | null;
    change_note: string | null;
    created_at: string;
}

export interface KindOption {
    value: string;
    label: string;
    description?: string;
}
