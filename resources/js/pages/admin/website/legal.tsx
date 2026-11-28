import { WebsiteSectionsPage } from '@/components/website-sections';
import type { SectionData } from '@/components/website-sections';

/** Website → Legal pages: Terms & Conditions and the Privacy Policy. Admins only. */
export default function LegalPagesContent({
    sections,
}: {
    sections: SectionData[];
}) {
    return (
        <WebsiteSectionsPage
            sections={sections}
            title="Legal pages"
            description="The Terms & Conditions and the Privacy Policy. These are legal texts: change them only once the new wording has been approved. Saving publishes straight away."
            what="the legal pages"
            sitePath="/"
            note="Their contents lists are built from the h3 headings."
        />
    );
}

LegalPagesContent.layout = {
    breadcrumbs: [{ title: 'Legal pages', href: '/admin/website/legal' }],
};
