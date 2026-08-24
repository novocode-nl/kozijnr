"use client"

import { useMemo } from "react"
import { useRouter, useSearchParams } from "next/navigation"
import { useTranslation } from "react-i18next"

import { cn } from "@/lib/utils"
import { adminLogin } from "@/lib/api"
import { buildLoginSchema, type LoginFormValues } from "@/lib/schemas/login"
import type { Locale } from "@/lib/i18n/locale"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Field, FieldDescription, FieldSeparator } from "@/components/ui/field"
import { AppleIcon, GoogleIcon } from "@/components/social-icons"
import { LoginSidePanel } from "@/components/login-side-panel"
import { ConfigForm } from "@/components/config-form/config-form"
import type { FieldConfig } from "@/lib/forms/types"
import { REDIRECT_PARAM, sanitizeRedirectTarget } from "@/lib/navigation/safe-redirect"

/**
 * Super-admin counterpart to components/login-form.tsx. Same `<ConfigForm>`
 * + `loginSchema` pattern (both realms authenticate with a plain
 * email/password pair — see config/packages/security.yaml's `super_admin`
 * firewall, same `json_login` shape as the tenant realm) — only the
 * `action` differs, posting to POST /api/admin/login instead of
 * POST /api/login.
 *
 * Rendered by app/login/page.tsx when the Host resolves to the admin
 * context — /login is the single path for both forms, this component is
 * never routed to directly.
 *
 * Same post-login `?redirect=` handling as components/login-form.tsx — see
 * that file's doc comment.
 */
const defaultValues: LoginFormValues = { email: "", password: "" }

export function AdminLoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const router = useRouter()
  const searchParams = useSearchParams()
  const redirectTarget = sanitizeRedirectTarget(searchParams.get(REDIRECT_PARAM))
  const { t, i18n } = useTranslation()
  const schema = useMemo(() => buildLoginSchema(i18n.language as Locale), [i18n.language])

  const fields: FieldConfig<LoginFormValues>[] = [
    {
      name: "email",
      label: t("login.emailLabel"),
      type: "email",
      placeholder: t("adminLogin.emailPlaceholder"),
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
              <h1 className="text-2xl font-bold">{t("adminLogin.title")}</h1>
              <p className="text-balance text-muted-foreground">
                {t("adminLogin.subtitle")}
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
              action={adminLogin}
              onSuccess={() => router.push(redirectTarget)}
              submitLabel={t("login.submit")}
              pendingLabel={t("login.pending")}
            />
            <FieldDescription className="text-center">
              <a href="#">{t("login.forgotPassword")}</a>
            </FieldDescription>
          </div>
          <LoginSidePanel />
        </CardContent>
      </Card>
    </div>
  )
}
