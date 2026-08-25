"use client"

import { useMemo } from "react"
import { useRouter, useSearchParams } from "next/navigation"
import { useTranslation } from "react-i18next"

import { cn } from "@/lib/utils"
import { login } from "@/lib/api"
import { buildLoginSchema, type LoginFormValues } from "@/lib/schemas/login"
import { setClientLocale, type Locale } from "@/lib/i18n/locale"
import { useTenantLoginImageUrl } from "@/hooks/use-tenant-login-image-url"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Field, FieldDescription, FieldSeparator } from "@/components/ui/field"
import { AppleIcon, GoogleIcon } from "@/components/social-icons"
import { LoginSidePanel } from "@/components/login-side-panel"
import { ConfigForm } from "@/components/config-form/config-form"
import type { FieldConfig } from "@/lib/forms/types"
import { REDIRECT_PARAM, sanitizeRedirectTarget } from "@/lib/navigation/safe-redirect"

/**
 * Built on the generic, config-driven `<ConfigForm>`. `login` (lib/api.ts)
 * is passed directly as the `<ConfigForm>` `action` since its `LoginResult`
 * shape is already structurally an `ActionResult`. A failed login returns a
 * generic `message` (no `fieldErrors` — the backend intentionally doesn't
 * distinguish "unknown email" from "wrong password"), which `<ConfigForm>`
 * surfaces as the shared banner.
 *
 * The header/social-buttons/separator sit outside the `<form>`
 * `<ConfigForm>` renders internally — they're disabled/non-interactive
 * decoration, not form controls, so this doesn't change submit behavior.
 *
 * On success, navigates to whatever page proxy.ts's route guard originally
 * bounced the visitor away from (carried as `?redirect=`), falling back to
 * `/` when there is none or it fails validation — see
 * lib/navigation/safe-redirect.ts for the open-redirect guard.
 *
 * KOZ-34: `login`'s response carries the tenant's `defaultLocale`
 * (App\TenantUser\Infrastructure\Controller\LoginController). Every new
 * login starts the app in that locale — never a previously chosen one, per
 * the ticket's scope (no per-user preference persists across sessions) —
 * so both the i18next instance and the persisted locale cookie are updated
 * here, before navigating away, the same way the language switcher
 * (components/nav-user.tsx) does it.
 */
const defaultValues: LoginFormValues = { email: "", password: "" }

export function LoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const router = useRouter()
  const searchParams = useSearchParams()
  const redirectTarget = sanitizeRedirectTarget(searchParams.get(REDIRECT_PARAM))
  const { t, i18n } = useTranslation()
  const schema = useMemo(() => buildLoginSchema(i18n.language as Locale), [i18n.language])

  // KOZ-34: fetched client-side (see hooks/use-tenant-login-image-url.ts
  // for why this is a `fetch()` + blob URL, not a plain `<img src>`).
  // `undefined` while loading / when none exists — LoginSidePanel falls
  // back to the placeholder in that case, so there's no broken-image flash.
  const loginImageUrl = useTenantLoginImageUrl()

  const fields: FieldConfig<LoginFormValues>[] = [
    {
      name: "email",
      label: t("login.emailLabel"),
      type: "email",
      placeholder: t("login.emailPlaceholder"),
      autoComplete: "email",
    },
    {
      name: "password",
      label: t("login.passwordLabel"),
      type: "password",
      autoComplete: "current-password",
    },
  ]

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <div className="flex flex-col gap-6 p-6 md:p-8">
            <div className="flex flex-col items-center gap-2 text-center">
              <h1 className="text-2xl font-bold">{t("login.welcomeTitle")}</h1>
              <p className="text-balance text-muted-foreground">
                {t("login.welcomeSubtitle")}
              </p>
            </div>
            {/* Disabled: no Apple/Google login backend yet, placeholder only. */}
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
              {t("login.orContinueWith")}
            </FieldSeparator>
            <ConfigForm
              fields={fields}
              schema={schema}
              defaultValues={defaultValues}
              action={login}
              onSuccess={(result) => {
                if (result) {
                  i18n.changeLanguage(result.defaultLocale)
                  setClientLocale(result.defaultLocale)
                }
                router.push(redirectTarget)
              }}
              submitLabel={t("login.submit")}
              pendingLabel={t("login.pending")}
            />
            <FieldDescription className="text-center">
              <a href="#">{t("login.forgotPassword")}</a>
            </FieldDescription>
          </div>
          <LoginSidePanel imageUrl={loginImageUrl} />
        </CardContent>
      </Card>
    </div>
  )
}
