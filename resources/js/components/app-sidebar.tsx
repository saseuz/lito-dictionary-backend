import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    Users,
    Shield,
    KeyRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { lookup } from '@/routes/admin/dictionary';
import { index as adminsIndex } from '@/routes/admin/admins';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as permissionsIndex } from '@/routes/admin/permissions';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { capabilities } = usePage().props;
    const items: NavItem[] = [...mainNavItems];
    if (capabilities['dictionary.view'])
        items.push({ title: 'Dictionary', href: lookup(), icon: BookOpen });
    if (capabilities['admins.view'])
        items.push({ title: 'Admins', href: adminsIndex(), icon: Users });
    if (capabilities['roles.view'])
        items.push({ title: 'Roles', href: rolesIndex(), icon: Shield });
    if (capabilities['permissions.view'])
        items.push({
            title: 'Permission catalog',
            href: permissionsIndex(),
            icon: KeyRound,
        });
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
