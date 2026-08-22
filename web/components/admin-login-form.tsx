"use client"

import { useRouter } from "next/navigation"

import { cn } from "@/lib/utils"
import { adminLogin } from "@/lib/api"
import { loginSchema, type LoginFormValues } from "@/lib/schemas/login"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Field, FieldDescription, FieldSeparator } from "@/components/ui/field"
import { AppleIcon, GoogleIcon } from "@/components/social-icons"
import { LoginSidePanel } from "@/components/login-side-panel"
import { ConfigForm } from "@/components/config-form/config-form"
import type { FieldConfig } from "@/lib/forms/types"

/**
 * Super-admin counterpart to components/login-form.tsx. Same
 * `<ConfigForm>` (KOZ-17) + `loginSchema` pattern (both realms
 * authenticate with a plain email/password pair — see
 * config/packages/security.yaml's `super_admin` firewall's `json_login`
 * config, which uses the same `username_path`/`password_path` shape as the
 * tenant realm) — only the `action` differs, posting to
 * POST /api/admin/login (lib/api.ts's `adminLogin`) instead of
 * POST /api/login.
 *
 * Rendered by app/login/page.tsx when the Host resolves to the admin
 * context (lib/context/app-context.ts) — /login is the single path for
 * both forms, this component is never routed to directly.
 *
 * KOZ-16 rework: rebuilt on shadcn's login-04 block, social-buttons-on-top
 * variant, keeping the block's default two-column layout (form left,
 * decorative image side panel right, hidden below md) — disabled
 * Apple/Google buttons (components/social-icons.tsx) above the form,
 * "Wachtwoord vergeten?" link where the block's default "Sign up" link
 * goes.
 *
 * KOZ-18 rework: form implementation replaced by `<ConfigForm>` — see
 * components/login-form.tsx's doc comment for the shared rationale
 * (structurally-compatible `LoginResult`/`ActionResult`, generic banner
 * for invalid-credentials errors, header/social/separator kept outside the
 * internal `<form>`). Purely a forms-logic change, the auth flow and
 * layout above are unchanged.
 */
const fields: FieldConfig<LoginFormValues>[] = [
  {
    name: "email",
    label: "E-mailadres",
    type: "email",
    placeholder: "naam@kozijnr.nl",
    autoComplete: "email",
  },
  {
    name: "password",
    label: "Wachtwoord",
    type: "password",
    autoComplete: "current-password",
  },
]

const defaultValues: LoginFormValues = { email: "", password: "" }

export function AdminLoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const router = useRouter()

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <div className="flex flex-col gap-6 p-6 md:p-8">
            <div className="flex flex-col items-center gap-2 text-center">
              <h1 className="text-2xl font-bold">Kozijnr Admin</h1>
              <p className="text-balance text-muted-foreground">
                Log in als beheerder
              </p>
            </div>
            {/*
              KOZ-16: social login bovenaan. Beide knoppen zijn disabled —
              er bestaat nog geen Apple- of Google-login-koppeling in de
              backend, dit is puur de visuele plek voor latere
              implementatie (Google expliciet vereist door het ticket).
            */}
            <Field className="grid grid-cols-2 gap-4">
              <Button variant="outline" type="button" disabled>
                <AppleIcon />
                Apple
              </Button>
              <Button variant="outline" type="button" disabled>
                <GoogleIcon />
                Google
              </Button>
            </Field>
            <FieldSeparator className="*:data-[slot=field-separator-content]:bg-card">
              Of ga verder met
            </FieldSeparator>
            <ConfigForm
              fields={fields}
              schema={loginSchema}
              defaultValues={defaultValues}
              action={adminLogin}
              onSuccess={() => router.push("/")}
              submitLabel="Inloggen"
              pendingLabel="Bezig met inloggen..."
            />
            <FieldDescription className="text-center">
              <a href="#">Wachtwoord vergeten?</a>
            </FieldDescription>
          </div>
          <LoginSidePanel />
        </CardContent>
      </Card>
    </div>
  )
}
