"use client"

import { useMemo } from "react"
import { useRouter, useSearchParams } from "next/navigation"
import { useTranslation } from "react-i18next"

import { cn } from "@/lib/utils"
import { login } from "@/lib/api"
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
