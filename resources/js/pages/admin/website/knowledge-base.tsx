import { WebsiteSectionsPage } from '@/components/website-sections';
import type { SectionData } from '@/components/website-sections';

/** Website → Knowledge Base page: its introduction and its search result. */
export default function KnowledgeBasePageContent({
    sections,
}: {
    sections: SectionData[];
}) {
    return (
        <WebsiteSectionsPage
            sections={sections}
            title="Knowledge Base page"
            description="The words at the top of the Knowledge Base, and how it shows in search results. Saving a section puts it on the site straight away."
            what="the Knowledge Base page"
            sitePath="/knowledge-base"
            note="The posts themselves are under Posts."
        />
    );
}

KnowledgeBasePageContent.layout = {
    breadcrumbs: [
        { title: 'Knowledge Base page', href: '/admin/website/knowledge-base' },
    ],
};
