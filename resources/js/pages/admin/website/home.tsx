import { WebsiteSectionsPage } from '@/components/website-sections';
import type { SectionData } from '@/components/website-sections';

/** Website → Home page: the home page's words and its search result. */
export default function HomePageContent({
    sections,
}: {
    sections: SectionData[];
}) {
    return (
        <WebsiteSectionsPage
            sections={sections}
            title="Home page"
            description="The words on the home page, section by section, and how it shows in search results. Saving a section puts it on the site straight away."
            what="the home page"
            sitePath="/"
            note="Featured members, the tagline and contact details have their own pages."
        />
    );
}

HomePageContent.layout = {
    breadcrumbs: [{ title: 'Home page', href: '/admin/website/home' }],
};
